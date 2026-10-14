<?php

declare(strict_types=1);

namespace DuplicateOrder\Twig;

use DuplicateOrder\DuplicateOrder as DuplicateOrderModule;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Thelia\Core\Form\FormServiceInterface;
use Thelia\Core\Translation\Translator;

/**
 * The "renew this order" action of an order page of the customer account: a button, and a
 * confirmation window holding the form posted to the duplication controller. The labels are
 * translated here, a module template being unable to reach its own catalogue through |trans.
 */
#[AsTwigComponent(name: 'DuplicateOrder', template: '@DuplicateOrderModule/components/DuplicateOrder.html.twig')]
class DuplicateOrder
{
    private const ROUTE = 'duplicate_orderduplicate_order_form';

    // The cart page of the theme; the home page when the theme names it otherwise.
    private const CART_ROUTE = 'checkout_cart';

    public ?int $orderId = null;

    public function __construct(
        private readonly FormServiceInterface $formService,
        private readonly Translator $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getForm(): FormView
    {
        return $this->formService
            ->getFormByName(DuplicateOrderForm::getName(), ['order-id' => $this->orderId])
            ->createView();
    }

    public function getAction(): string
    {
        return $this->urlGenerator->generate(self::ROUTE);
    }

    public function getSuccessUrl(): string
    {
        try {
            return $this->urlGenerator->generate(self::CART_ROUTE);
        } catch (RouteNotFoundException) {
            return '/';
        }
    }

    public function getErrorUrl(): string
    {
        return $this->requestStack->getCurrentRequest()?->getRequestUri() ?? '/';
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        $labels = [
            'button' => 'Renew my order',
            'title' => 'Renew this order?',
            'warning' => 'Your current cart will be emptied and replaced by the products of this order. Products no longer sold are left out.',
            'cancel' => 'Cancel',
            'confirm' => 'Renew my order',
        ];

        return array_map(
            fn (string $label): string => $this->translator->trans($label, [], DuplicateOrderModule::DOMAIN_NAME),
            $labels,
        );
    }
}
