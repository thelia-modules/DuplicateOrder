<?php

namespace DuplicateOrder\Controller\Front;

use DuplicateOrder\DuplicateOrder;
use DuplicateOrder\Form\Front\DuplicateOrderForm;
use DuplicateOrder\Service\NothingToRenewException;
use DuplicateOrder\Service\OrderRenewal;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

#[Route(path: '/order/duplicate', name: 'duplicate_order')]
class DuplicateOrderController extends BaseFrontController
{
    /**
     * A visitor sent to sign in from the renew action comes back here with a GET: there is
     * nothing to submit any more, so they land on their orders instead of an error page.
     */
    #[Route(path: '', name: 'duplicate_order_back', methods: ['GET'])]
    public function backToOrders(UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        $this->checkAuth();

        try {
            return new RedirectResponse($urlGenerator->generate('account_orders'));
        } catch (RouteNotFoundException) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl('/'));
        }
    }

    /**
     * Replaces the cart with the products of the order (OrderRenewal). The products left out and
     * those put back with a lower quantity are named to the customer on the cart page; when
     * nothing was renewed, the cart is as it was and the reason is told at the top of the order page.
     */
    #[Route(path: '', name: 'duplicate_order_form', methods: ['POST'])]
    public function duplicateOrder(
        OrderRenewal $renewal,
        Session $session,
        ParserContext $parserContext
    ): ?RedirectResponse
    {
        // Only a signed-in customer has orders to renew: a visitor is sent to sign in.
        $this->checkAuth();

        $duplicateForm = $this->createForm(DuplicateOrderForm::getName());
        $products = [];

        try {
            $data = $this->validateForm($duplicateForm, 'post')->getData();

            // The id comes from the page: only an order of the signed-in customer is
            // looked up, so another customer's order reads exactly like a missing one.
            $order = OrderQuery::create()
                ->filterByCustomerId($this->getSecurityContext()->getCustomerUser()->getId())
                ->findPk((int) $data['order-id']);

            if (null === $order) {
                $message = self::trans('This order cannot be renewed.');
            } else {
                $report = $renewal->renew($order, $session);

                if (!$report->isComplete()) {
                    $session->getFlashBag()->add(DuplicateOrder::REPORT_FLASH, $report->toArray());
                }

                return $this->generateSuccessRedirect($duplicateForm);
            }
        } catch (FormValidationException $e) {
            $message = $e->getMessage();
        } catch (NothingToRenewException $e) {
            $message = $e->getMessage();
            $products = $e->leftOut;
        } catch (\Throwable $e) {
            $message = self::trans('Your order could not be renewed, your cart is unchanged. Please try again.');

            Tlog::getInstance()->error(sprintf('Error during duplication process : %s', $e->getMessage()));
        }

        $session->getFlashBag()->add(DuplicateOrder::ORDER_FLASH, ['message' => $message, 'products' => $products]);
        $duplicateForm->setErrorMessage($message);

        $parserContext
            ->addForm($duplicateForm)
            ->setGeneralError($message)
        ;

        return $this->generateErrorRedirect($duplicateForm);
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, DuplicateOrder::DOMAIN_NAME);
    }
}
