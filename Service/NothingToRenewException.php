<?php

namespace DuplicateOrder\Service;

/**
 * No line of the order can be put back in the cart: the renewal is given up, the cart is
 * left as it was. The message says so; the products left out come with the reason of each.
 */
final class NothingToRenewException extends \RuntimeException
{
    /**
     * @param list<string> $leftOut each product left out, with the reason
     */
    public function __construct(string $message, public readonly array $leftOut)
    {
        parent::__construct($message);
    }
}
