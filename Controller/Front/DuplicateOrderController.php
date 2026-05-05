<?php

namespace DuplicateOrder\Controller\Front;

use DuplicateOrder\Event\DuplicateOrderEvent;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Log\Tlog;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

#[Route(path: '/order/duplicate', name: 'duplicate_order')]
class DuplicateOrderController extends BaseFrontController
{
    #[Route(path: '', name: 'duplicate_order_form')]
    public function duplicateOrder(
        EventDispatcherInterface $dispatcher,
        Session $session,
        ParserContext $parserContext
    ): ?RedirectResponse
    {
        $duplicateForm = $this->createForm(DuplicateOrderForm::getName());

        try {

            $data = $this->validateForm($duplicateForm)->getData();

            $orderId = $data['order-id'];
            $order = OrderQuery::create()->findOneById($orderId);

            $orderProducts = $order->getOrderProducts();

            if ($orderProducts !== null) {
                $cart = $session->getSessionCart($dispatcher);
                $cartEvent = new CartEvent($cart);

                if (null !== $cart->getId()) {
                    $dispatcher->dispatch( $cartEvent,TheliaEvents::CART_CLEAR);
                    $cart = $session->getSessionCart($dispatcher);
                }

                if ($cart->isNew()) {
                    $dispatcher->dispatch(new CartPersistEvent($cart), TheliaEvents::CART_PERSIST);
                }

                $orderProductsArray = array();

                //Fill cart with order products
                /** @var OrderProduct $orderProduct */
                foreach ($orderProducts as $orderProduct) {
                    $newEvent = new CartEvent($cart);
                    $newEvent->setQuantity($orderProduct->getQuantity());
                    $product = ProductQuery::create()
                        ->filterByVisible(true)
                        ->filterByRef($orderProduct->getProductRef())
                        ->findOne();

                    if (null === $product) {
                        continue;
                    }

                    $pse = ProductSaleElementsQuery::create()
                        ->filterById($orderProduct->getProductSaleElementsId())
                        ->findOne();

                    if (null === $pse) {
                        $pse = ProductSaleElementsQuery::create()
                            ->filterByRef($orderProduct->getProductSaleElementsRef())
                            ->findOne();
                    }

                    if (null === $pse) {
                        continue;
                    }

                    $newEvent->setProductId($product->getId());
                    $newEvent->setNewness(true);
                    $newEvent->setAppend(false);
                    $newEvent->setProductSaleElementsId($pse->getId());

                    $dispatcher->dispatch($newEvent,TheliaEvents::CART_ADDITEM);
                    $orderProductsArray[] = $orderProduct;
                }

                $cartItems = $cart->getCartItems()->getData();

                $dispatcher->dispatch(
                    new DuplicateOrderEvent($orderProductsArray, $cartItems),
                    DuplicateOrderEvent::DUPLICATE_PRODUCT
                );

                return $this->generateSuccessRedirect($duplicateForm);
            }

            $message = "Order product was not found";
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        Tlog::getInstance()->error(
            sprintf(
                "Error during duplication process : %s",
                $message
            )
        );

        $duplicateForm->setErrorMessage($message);

        $parserContext
            ->addForm($duplicateForm)
            ->setGeneralError($message)
        ;

        return $this->generateErrorRedirect($duplicateForm);
    }
}
