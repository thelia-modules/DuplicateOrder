<?php

namespace DuplicateOrder\Form\Front;


use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class DuplicateOrderForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'order-id',
                'text',
                array(
                    'constraints'   => array(new NotBlank()),
                    'required'      => true
                )
            );
    }

    public function getName()
    {
        return "duplicate_order";
    }
}
