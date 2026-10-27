<?php

namespace DuplicateOrder\Service;

use DuplicateOrder\DuplicateOrder;
use DuplicateOrder\Event\DuplicateOrderEvent;
use DuplicateOrder\Event\RenewedQuantityEvent;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Replaces the cart of the session with the products of an order, all at once or not at all:
 * the cart is emptied and refilled in one transaction.
 *
 * When the shop checks the stock (`check-available-stock`), a line out of stock is left out and
 * a line asking for more than the stock is put back with the stock, or the lower quantity a
 * listener of RenewedQuantityEvent gives (0: left out). A line the cart refuses (a listener of
 * CART_ADDITEM throwing InvalidCartException before the core adds the line, the stock check of
 * the core) or a product no longer sold is left out, and the others go on. When no line can be
 * put back, or on any other failure, the transaction is rolled back and the session names its
 * cart and the token of its cookie again: the cart is as it was.
 */
class OrderRenewal
{
    /** The token Thelia\Core\EventListener\ResponseListener turns into the cart cookie. */
    private const CART_COOKIE_TOKEN = 'cart_use_cookie';

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return RenewalReport the products left out and those put back with a lower quantity
     *
     * @throws NothingToRenewException when no line of the order can be put back
     * @throws \Throwable on any other failure, once the cart is back as it was
     */
    public function renew(Order $order, Session $session): RenewalReport
    {
        $originalCartId = $session->get(Session::SESSION_CART_ID_NAME);
        $hadCookieToken = $session->has(self::CART_COOKIE_TOKEN);
        $originalCookieToken = $session->get(self::CART_COOKIE_TOKEN);
        $connection = Propel::getWriteConnection(CartTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $cart = $session->getSessionCart($this->dispatcher);

            if (null !== $cart->getId()) {
                $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_CLEAR);
                $cart = $session->getSessionCart($this->dispatcher);
            }

            if ($cart->isNew()) {
                $this->dispatcher->dispatch(new CartPersistEvent($cart), TheliaEvents::CART_PERSIST);
            }

            $added = [];
            $leftOut = [];
            $adjusted = [];

            /** @var OrderProduct $orderProduct */
            foreach ($order->getOrderProducts() as $orderProduct) {
                $stillSold = $this->stillSold($orderProduct);

                if (null === $stillSold) {
                    $leftOut[] = self::leftOut($orderProduct, self::trans('no longer sold'));

                    continue;
                }

                $quantity = (float) $orderProduct->getQuantity();

                if ($this->checksStock($stillSold['saleElements'])) {
                    $stock = (float) $stillSold['saleElements']->getQuantity();

                    if ($stock <= 0) {
                        $leftOut[] = self::leftOut($orderProduct, self::trans('out of stock'));

                        continue;
                    }

                    if ($quantity > $stock) {
                        $renewed = new RenewedQuantityEvent($cart, $stillSold['saleElementsId'], $quantity, $stock);
                        $this->dispatcher->dispatch($renewed, RenewedQuantityEvent::NAME);

                        if ($renewed->getQuantity() <= 0) {
                            $leftOut[] = self::leftOut($orderProduct, self::trans('not enough stock'));

                            continue;
                        }

                        $adjusted[$orderProduct->getId()] = self::trans('%product (%ordered ordered, %renewed put back)', [
                            '%product' => self::title($orderProduct),
                            '%ordered' => self::number($quantity),
                            '%renewed' => self::number($renewed->getQuantity()),
                        ]);
                        $quantity = $renewed->getQuantity();
                    }
                }

                $event = new CartEvent($cart);
                $event->setQuantity($quantity);
                $event->setNewness(true);
                $event->setAppend(false);
                // The product too, as the controller of 4.1.1 did: a listener of CART_ADDITEM above the
                // core reads it before the core sets it from the sale element.
                $event->setProductId($stillSold['productId']);
                $event->setProductSaleElementsId($stillSold['saleElementsId']);

                // Refused before the cart writes the line: left out, the others go on. Any other
                // exception reaches the rollback below.
                try {
                    $this->dispatcher->dispatch($event, TheliaEvents::CART_ADDITEM);
                } catch (InvalidCartException $e) {
                    unset($adjusted[$orderProduct->getId()]);
                    $leftOut[] = self::leftOut($orderProduct, $e->getMessage());

                    continue;
                } catch (NotEnoughStockException) {
                    unset($adjusted[$orderProduct->getId()]);
                    $leftOut[] = self::leftOut($orderProduct, self::trans('not enough stock'));

                    continue;
                }

                $added[] = $orderProduct;
            }

            if ([] === $added) {
                throw new NothingToRenewException(self::trans('None of the products of this order can be put back in your cart:'), $leftOut);
            }

            $this->dispatcher->dispatch(
                new DuplicateOrderEvent($added, $cart->getCartItems()->getData()),
                DuplicateOrderEvent::DUPLICATE_PRODUCT
            );

            $connection->commit();

            return new RenewalReport($leftOut, array_values($adjusted));
        } catch (\Throwable $e) {
            $connection->rollBack();

            // The emptied cart is back in the database; the session still names the cart
            // made in its place, which the rollback removed.
            if (null === $originalCartId) {
                $session->remove(Session::SESSION_CART_ID_NAME);
            } else {
                $session->set(Session::SESSION_CART_ID_NAME, $originalCartId);
            }

            // CART_PERSIST wrote the token of the removed cart: left there, the response makes it the cart cookie.
            if ($hadCookieToken) {
                $session->set(self::CART_COOKIE_TOKEN, $originalCookieToken);
            } else {
                $session->remove(self::CART_COOKIE_TOKEN);
            }

            throw $e;
        }
    }

    /**
     * The product and the sale element the order line is put back with: its own sale element, or
     * one of the same reference on the same product, the product still visible. The rules of the
     * shop on the catalogue are the cart's to apply, on the addition.
     *
     * @return array{productId: int, saleElementsId: int, saleElements: ProductSaleElements}|null null when no longer sold
     */
    private function stillSold(OrderProduct $orderProduct): ?array
    {
        $product = ProductQuery::create()
            ->filterByVisible(1)
            ->filterByRef($orderProduct->getProductRef())
            ->findOne();

        if (null === $product) {
            return null;
        }

        $saleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterById($orderProduct->getProductSaleElementsId())
            ->findOne()
            ?? ProductSaleElementsQuery::create()
                ->filterByProductId($product->getId())
                ->filterByRef($orderProduct->getProductSaleElementsRef())
                ->findOne();

        return null === $saleElements ? null : ['productId' => (int) $product->getId(), 'saleElementsId' => (int) $saleElements->getId(), 'saleElements' => $saleElements];
    }

    /**
     * The stock of a sale element limits the renewal as it limits the cart (Flexy's CartStockService):
     * when the shop checks it, for a product that is not virtual.
     */
    private function checksStock(ProductSaleElements $saleElements): bool
    {
        return ConfigQuery::checkAvailableStock() && !$saleElements->getProduct()?->getVirtual();
    }

    private static function leftOut(OrderProduct $orderProduct, string $reason): string
    {
        return sprintf('%s (%s)', self::title($orderProduct), $reason);
    }

    private static function title(OrderProduct $orderProduct): string
    {
        return $orderProduct->getTitle() ?: $orderProduct->getProductRef();
    }

    private static function number(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, DuplicateOrder::DOMAIN_NAME);
    }
}
