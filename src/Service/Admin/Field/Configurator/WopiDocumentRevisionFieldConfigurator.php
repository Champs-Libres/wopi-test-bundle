<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Admin\Field\Configurator;

use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentRevisionField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use SimpleThings\EntityAudit\AuditReader;

use function count;

final class WopiDocumentRevisionFieldConfigurator implements FieldConfiguratorInterface
{
    private AuditReader $auditReader;

    public function __construct(AuditReader $auditReader)
    {
        $this->auditReader = $auditReader;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $revision = $this->auditReader->getEntityHistory(Document::class, $field->getValue());

        $field->setFormattedValue(count($revision));
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return WopiDocumentRevisionField::class === $field->getFieldFqcn();
    }
}
