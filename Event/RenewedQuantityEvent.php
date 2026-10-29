<?php

namespace DuplicateOrder\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Model\Cart;

/**
 * A line of the renewed order asks for more than the stock holds: its quantity is brought down
 * to the stock, and a listener may lower it further, to a quantity the shop sells (a multiple of
 * a pack size…). A quantity of 0 or less leaves the line out.
 */
final class RenewedQuantityEvent extends Event
{
    public const NAME = 'duplicate_order.renewed_quantity';

    private float $quantity;

    public function __construct(
        public readonly Cart $cart,
        public readonly int $productSaleElementsId,
        public readonly float $orderedQuantity,
        public readonly float $stock,
    ) {
        $this->quantity = max(0.0, $stock);
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * Never above the stock: a listener lowers the quantity, it does not raise it.
     */
    public function setQuantity(float $quantity): void
    {
        $this->quantity = max(0.0, min($quantity, $this->stock));
    }
}
