<?php

namespace DuplicateOrder\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class OrderDetailsHook extends BaseHook
{
    public function onAccountOrderBottom(HookRenderEvent $event)
    {
        $content = $this->render("duplicate-order.html", ["orderId"=>$event->getArgument("order")]);

        $event->add($content);
    }
}
