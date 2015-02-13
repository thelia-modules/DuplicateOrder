<?php

namespace DuplicateOrder\Controller\Front;

use DuplicateOrder\Event\DuplicateOrderEvent;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

class DuplicateOrderController extends BaseFrontController
{
    public function duplicateOrder()
    {
        $message = null;

        $duplicateForm = new DuplicateOrderForm($this->getRequest());

        try {

            $form = $this->validateForm($duplicateForm);
            $data = $form->getData($form);

            $orderId = $data['order-id'];
            $order = OrderQuery::create()->findOneById($orderId);

            $orderProducts = $order->getOrderProducts();

            if ($orderProducts !== null) {

                $cart = $this->getSession()->getSessionCart($this->getDispatcher());
                $cItems = $cart->getCartItemsJoinProductSaleElements();

                //Delete items in cart
                foreach ($cItems as $cItem) {
                    $cartDelete = new Cart();
                    $cartDelete->addCartItem($cItem);
                    $cartEvent = new CartEvent($cartDelete);
                    $this->dispatch(TheliaEvents::CART_DELETEITEM, $cartEvent);
                }

                $cart->clearCartItems();

                $orderProductsArray = array();

                //Fill cart with order products
                /** @var \Thelia\Model\OrderProduct $orderProduct */
                foreach ($orderProducts as $orderProduct) {

                    $newEvent = new CartEvent($cart);
                    $newEvent->setQuantity($orderProduct->getQuantity());
                    $product = ProductQuery::create()->findOneByRef($orderProduct->getProductRef());
                    $newEvent->setProduct($product->getId());
                    $newEvent->setNewness(1);
                    $newEvent->setProductSaleElementsId($orderProduct->getProductSaleElementsId());

                    $this->dispatch(TheliaEvents::CART_ADDITEM, $newEvent);

                    $orderProductsArray[] = $orderProduct;
                }

                $cartItems = $newEvent->getCart()->getCartItems()->getData();

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
