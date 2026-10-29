<?php

declare(strict_types=1);

namespace DuplicateOrder\Hook\Theme;

use DuplicateOrder\DuplicateOrder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * At the top of the order page, once: why the last renewal changed nothing (the cart is as it
 * was), with the products left out and the reason of each. At the top, where the customer lands
 * back: the renew button is at the foot of a long order.
 */
final readonly class RenewalErrorThemeHook implements ThemeHookInterface
{
    public const HOOK = 'account-order.top';

    public function __construct(
        private Environment $twig,
        private RequestStack $requestStack,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = null !== $request && $request->hasSession() ? $request->getSession() : null;

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return '';
        }

        $errors = array_map(
            static fn (mixed $error): array => [
                'message' => (string) (\is_array($error) ? ($error['message'] ?? '') : $error),
                'products' => \is_array($error) ? array_map('strval', $error['products'] ?? []) : [],
            ],
            $session->getFlashBag()->get(DuplicateOrder::ORDER_FLASH),
        );

        return [] === $errors ? '' : $this->twig->render('@DuplicateOrderModule/theme_hook/renewal-error.html.twig', ['errors' => $errors]);
    }
}
