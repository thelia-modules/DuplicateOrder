<?php

declare(strict_types=1);

namespace DuplicateOrder\Hook\Theme;

use DuplicateOrder\DuplicateOrder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\Translation\Translator;
use Twig\Environment;

/**
 * On the cart page, once, what the last renewal changed against the order: the products left
 * out (an error) and those put back with the quantity in stock (a warning). Read from the flash
 * the renew action leaves, so a reload of the cart shows it no more.
 */
final readonly class RenewalReportThemeHook implements ThemeHookInterface
{
    public const HOOK = 'cart.top';

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

        $removed = [];
        $adjusted = [];

        foreach ($session->getFlashBag()->get(DuplicateOrder::REPORT_FLASH) as $report) {
            $removed = [...$removed, ...array_map('strval', $report['removed'] ?? [])];
            $adjusted = [...$adjusted, ...array_map('strval', $report['adjusted'] ?? [])];
        }

        if ([] === $removed && [] === $adjusted) {
            return '';
        }

        return $this->twig->render('@DuplicateOrderModule/theme_hook/renewal-report.html.twig', [
            'removed' => $removed,
            'adjusted' => $adjusted,
            'labels' => [
                'removed' => self::trans('These products of the order could not be put back in your cart:'),
                'adjusted' => self::trans('These products were put back with the quantity in stock:'),
            ],
        ]);
    }

    private static function trans(string $message): string
    {
        return Translator::getInstance()->trans($message, [], DuplicateOrder::DOMAIN_NAME);
    }
}
