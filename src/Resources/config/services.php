<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ChampsLibres\WopiLib\Configuration\WopiConfigurationInterface;
use ChampsLibres\WopiLib\Service\Contract\WopiInterface;
use ChampsLibres\WopiTestBundle\Service\Configuration\ConfigurableWopiConfiguration;
use ChampsLibres\WopiTestBundle\Service\Wopi;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services
        ->defaults()
        ->autoconfigure(true)
        ->autowire(true);

    $services
        ->load('ChampsLibres\\WopiTestBundle\\Service\\', __DIR__ . '/../../Service');

    $services
        ->load('ChampsLibres\\WopiTestBundle\\Controller\\', __DIR__ . '/../../Controller')
        ->tag('controller.service_arguments');

    $services
        ->load('ChampsLibres\\WopiTestBundle\\Service\\Admin\\Field\\Configurator\\', __DIR__ . '/../../Service/Admin/Field/Configurator')
        ->tag('ea.field_configurator');

    $services
        ->alias(WopiInterface::class, Wopi::class);

    $services
        ->set(ConfigurableWopiConfiguration::class)
        ->decorate(WopiConfigurationInterface::class)
        ->arg('$properties', service('.inner'));
};
