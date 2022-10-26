<?php

namespace DuplicateOrder\Form\Front;


use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class DuplicateOrderForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'order-id',
                TextType::class,
                array(
                    'constraints'   => array(new NotBlank()),
                    'required'      => true
                )
            );
    }

    public static function getName()
    {
        return "duplicate_order";
    }
}
