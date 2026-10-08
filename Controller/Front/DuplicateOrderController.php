<?php

namespace DuplicateOrder\Controller\Front;

use DuplicateOrder\Event\DuplicateOrderEvent;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

#[Route(path: '/order/duplicate', name: 'duplicate_order')]
class DuplicateOrderController extends BaseFrontController
{
    /**
     * A visitor sent to sign in from the renew action comes back here with a GET: there is
     * nothing to submit any more, so they land on their orders instead of an error page.
     */
    #[Route(path: '', name: 'duplicate_order_back', methods: ['GET'])]
    public function backToOrders(UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        $this->checkAuth();

        try {
            return new RedirectResponse($urlGenerator->generate('account_orders'));
        } catch (RouteNotFoundException) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/'));
        }
    }

    #[Route(path: '', name: 'duplicate_order_form', methods: ['POST'])]
    public function duplicateOrder(
        EventDispatcherInterface $dispatcher,
        Session $session,
        ParserContext $parserContext
    ): ?RedirectResponse
    {
        // Only a signed-in customer has orders to renew: a visitor is sent to sign in.
        $this->checkAuth();

        $duplicateForm = $this->createForm(DuplicateOrderForm::getName());

        try {
            $data = $this->validateForm($duplicateForm, 'post')->getData();

            // The id comes from the page: only an order of the signed-in customer is
            // looked up, so another customer's order reads exactly like a missing one.
            $order = OrderQuery::create()
                ->filterByCustomerId($this->getSecurityContext()->getCustomerUser()->getId())
                ->findPk((int) $data['order-id']);

            if (null !== $order) {
                $orderProducts = $order->getOrderProducts();

                $cart = $session->getSessionCart($dispatcher);
                $cartEvent = new CartEvent($cart);

                if (null !== $cart->getId()) {
                    $dispatcher->dispatch($cartEvent, TheliaEvents::CART_CLEAR);
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
                        ->filterByVisible(1)
                        ->filterByRef($orderProduct->getProductRef())
                        ->findOne();

                    if (null === $product) {
                        continue;
                    }

                    $pse = ProductSaleElementsQuery::create()
                        ->filterByProductId($product->getId())
                        ->filterById($orderProduct->getProductSaleElementsId())
                        ->findOne();

                    if (null === $pse) {
                        $pse = ProductSaleElementsQuery::create()
                            ->filterByProductId($product->getId())
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

                    $dispatcher->dispatch($newEvent, TheliaEvents::CART_ADDITEM);
                    $orderProductsArray[] = $orderProduct;
                }

                $cartItems = $cart->getCartItems()->getData();

                $dispatcher->dispatch(
                    new DuplicateOrderEvent($orderProductsArray, $cartItems),
                    DuplicateOrderEvent::DUPLICATE_PRODUCT
                );

                return $this->generateSuccessRedirect($duplicateForm);
            }

            $message = "Order was not found";
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            Tlog::getInstance()->error(
                sprintf(
                    "Error during duplication process : %s",
                    $message
                )
            );
        }

        $duplicateForm->setErrorMessage($message);

        $parserContext
            ->addForm($duplicateForm)
            ->setGeneralError($message)
        ;

        return $this->generateErrorRedirect($duplicateForm);
    }
}
