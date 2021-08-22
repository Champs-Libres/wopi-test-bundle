<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\DependencyInjection;

use ChampsLibres\WopiTestBundle\Entity\Document;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class WopiTestExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @phpstan-ignore-next-line
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $doctrine = $container->getExtensionConfig('doctrine');

        $doctrine[0]['orm']['mappings']['WopiTestBundle'] = [
            'type' => 'annotation',
            'dir' => __DIR__ . '/../Entity',
            'prefix' => 'ChampsLibres\WopiTestBundle\Entity',
            'alias' => 'WopiTestBundle',
        ];

        $container->prependExtensionConfig('doctrine', $doctrine[0]);

        $simpleThingsEntityAudit = $container->getExtensionConfig('simple_things_entity_audit');

        $simpleThingsEntityAudit[0]['audited_entities'][] = Document::class;
        $simpleThingsEntityAudit[0]['global_ignore_columns'][] = 'lock';

        $container->prependExtensionConfig('simple_things_entity_audit', $simpleThingsEntityAudit[0]);
    }
}
