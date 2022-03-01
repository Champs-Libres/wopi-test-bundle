<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Admin\Field\Configurator;

use ChampsLibres\WopiLib\Contract\Service\DocumentManagerInterface;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentRevisionTimestampField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

final class WopiDocumentRevisionTimestampFieldConfigurator implements FieldConfiguratorInterface
{
    private DocumentManagerInterface $documentManager;

    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field
            ->setFormattedValue(
                $this->documentManager->getLastModifiedDate($entityDto->getInstance())->format('Y/m/d H:i:s')
            );
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return WopiDocumentRevisionTimestampField::class === $field->getFieldFqcn();
    }
}
