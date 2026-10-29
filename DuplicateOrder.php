<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace DuplicateOrder;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class DuplicateOrder extends BaseModule
{
    public const DOMAIN_NAME = 'duplicateorder';

    /**
     * Flash read on the cart page (cart.top, Hook/Theme/RenewalReportThemeHook): what the last
     * renewal left out and what it put back with a lower quantity (RenewalReport::toArray()).
     */
    public const REPORT_FLASH = 'duplicate_order_report';

    /**
     * Flash read at the top of the order page (account-order.top, Hook/Theme/RenewalErrorThemeHook):
     * why nothing was renewed, and the products left out with the reason.
     */
    public const ORDER_FLASH = 'duplicate_order';

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
