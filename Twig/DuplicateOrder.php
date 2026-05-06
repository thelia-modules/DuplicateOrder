<?php

declare(strict_types=1);

namespace DuplicateOrder\Twig;

use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Thelia\Core\Form\FormServiceInterface;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: "DuplicateOrder", template: "@DuplicateOrderModule/components/DuplicateOrder.html.twig")]
class DuplicateOrder
{
    use ComponentWithFormTrait;

    public ?int $orderId = null;

    public function __construct(
        private readonly FormServiceInterface $formService,
    ) {}

    public function instantiateForm(): FormInterface
    {
        $form = $this->formService->getFormByName(
            DuplicateOrderForm::getName(),
            [
                "order-id" => $this->orderId,
            ],
        );

        return $form;
    }
}
