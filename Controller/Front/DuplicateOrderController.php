<?php

namespace DuplicateOrder\Controller\Front;

use DuplicateOrder\Event\DuplicateOrderEvent;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartPersistEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

class DuplicateOrderController extends BaseFrontController
{
    public function duplicateOrder()
    {
        $message = null;

        $duplicateForm = new DuplicateOrderForm($this->getRequest());

        try {

            $data = $this->validateForm($duplicateForm)->getData();

            $orderId = $data['order-id'];
            $order = OrderQuery::create()->findOneById($orderId);

            $orderProducts = $order->getOrderProducts();

            if ($orderProducts !== null) {
                $dispatcher = $this->getDispatcher();
                $cart = $this->getSession()->getSessionCart($this->getDispatcher());
                $cartEvent = new CartEvent($cart);

                if (null !== $cart->getId()) {
                    $dispatcher->dispatch(TheliaEvents::CART_CLEAR, $cartEvent);
                    $cart = $this->getRequest()->getSession()->getSessionCart($dispatcher);
                }

                if ($cart->isNew()) {
                    $persistEvent = new CartPersistEvent($cart);
                    $dispatcher->dispatch(TheliaEvents::CART_PERSIST, $persistEvent);
                }

                $orderProductsArray = array();

                //Fill cart with order products
                /** @var \Thelia\Model\OrderProduct $orderProduct */
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

                    $newEvent->setProduct($product->getId());
                    $newEvent->setNewness(true);
                    $newEvent->setAppend(false);
                    $newEvent->setProductSaleElementsId($pse->getId());

                    $this->dispatch(TheliaEvents::CART_ADDITEM, $newEvent);

                    $orderProductsArray[] = $orderProduct;
                }

                $cartItems = $cart->getCartItems()->getData();

                $duplicateEvent = new DuplicateOrderEvent($orderProductsArray, $cartItems);
                $this->dispatch(DuplicateOrderEvent::DUPLICATE_PRODUCT, $duplicateEvent);

                return $this->generateSuccessRedirect($duplicateForm);
            }

        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message !== null) {
            Tlog::getInstance()->error(
                sprintf(
                    "Error during duplication process : %s. Exception was %s",
                    $message,
                    $e->getMessage()
                )
            );

            $duplicateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($duplicateForm)
                ->setGeneralError($message)
            ;
        }
    }
}
