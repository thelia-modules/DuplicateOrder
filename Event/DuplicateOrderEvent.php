<?php

namespace DuplicateOrder\Event;

use Thelia\Core\Event\ActionEvent;

class DuplicateOrderEvent extends ActionEvent
{
    protected $orderProducts;

    protected $cartItems;

    public function __construct($orderProducts, $cartItems)
    {
        $this->orderProducts = $orderProducts;
        $this->cartItems = $cartItems;
    }

    public function getOrderProducts()
    {
        return $this->orderProducts;
    }

    public function setOrderProducts($orderProducts)
    {
        $this->orderProducts = $orderProducts;
    }

    public function getCartItems()
    {
        return $this->cartItems;
    }

    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
    }

    const DUPLICATE_PRODUCT = "duplicate.product";
}
