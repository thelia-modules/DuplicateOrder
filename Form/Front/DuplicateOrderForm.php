<?php

namespace DuplicateOrder\Form\Front;


use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Thelia\Form\BaseForm;

class DuplicateOrderForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'order-id',
                IntegerType::class,
                array(
                    'constraints'   => array(new NotBlank(), new Positive()),
                    'required'      => true
                )
            );
    }

    public static function getName(): string
    {
        return "duplicate_order";
    }
}
