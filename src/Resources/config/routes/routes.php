<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Wopi routes callback.
 *
 * phpcs:disable Generic.Files.LineLength.TooLong
 */
return static function (RoutingConfigurator $routes) {
    $routes
        ->import(__DIR__ . '/../../../Controller/Admin', 'annotation');
};
