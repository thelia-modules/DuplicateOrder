<?php

declare(strict_types=1);

namespace DuplicateOrder\Hook\Theme;

use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Puts the "renew my order" action at the foot of an order page of the customer account.
 *
 * The theme passes the order as its page read it (the API resource, an array or an
 * object); only its id is used. The page itself has already checked that the order is the
 * signed-in customer's, and the duplication controller checks it again on submission.
 */
final readonly class DuplicateOrderThemeHook implements ThemeHookInterface
{
    public const HOOK = 'account-order.bottom';

    public function __construct(
        private Environment $twig,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $order = $parameters['order'] ?? null;
        $orderId = match (true) {
            \is_array($order) => $order['id'] ?? null,
            \is_object($order) && method_exists($order, 'getId') => $order->getId(),
            \is_object($order) => $order->id ?? null,
            default => null,
        };

        if (!is_numeric($orderId) || (int) $orderId <= 0) {
            return '';
        }

        return $this->twig->render('@DuplicateOrderModule/theme_hook/duplicate-order.html.twig', [
            'orderId' => (int) $orderId,
        ]);
    }
}
