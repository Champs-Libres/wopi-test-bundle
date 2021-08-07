<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use ChampsLibres\WopiTestBundle\Controller\Editor;
use ChampsLibres\WopiTestBundle\Controller\Files;
use ChampsLibres\WopiTestBundle\Controller\Hosting;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Wopi routes callback.
 *
 * phpcs:disable Generic.Files.LineLength.TooLong
 */
return static function (RoutingConfigurator $routes) {
    $routes
        ->add('capabilities', '/hosting/capabilities')
        ->controller([Hosting::class, 'capabilities']);

    $routes
        ->add('editorGeneratorRandomFilename', '/editor')
        ->controller([Editor::class, 'generateRandomFilename']);

    $routes
        ->add('editor', '/editor/{fileName}/{extension}')
        ->defaults([
            'extension' => 'odt',
        ])
        ->controller(Editor::class);
};
