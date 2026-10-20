<?php

namespace DuplicateOrder\Service;

/**
 * What a renewal changed against the order: the products left out of the cart and the products
 * put back with a lower quantity, each written for the customer.
 */
final class RenewalReport
{
    /**
     * @param list<string> $removed  each product left out, with the reason
     * @param list<string> $adjusted each product put back with a lower quantity, with both quantities
     */
    public function __construct(
        public readonly array $removed = [],
        public readonly array $adjusted = [],
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->removed && [] === $this->adjusted;
    }

    /**
     * @return array{removed: list<string>, adjusted: list<string>}
     */
    public function toArray(): array
    {
        return ['removed' => $this->removed, 'adjusted' => $this->adjusted];
    }
}
