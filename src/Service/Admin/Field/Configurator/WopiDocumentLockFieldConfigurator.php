<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Admin\Field\Configurator;

use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentLockField;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

final class WopiDocumentLockFieldConfigurator implements FieldConfiguratorInterface
{
    private DocumentRepository $documentRepository;

    public function __construct(DocumentRepository $documentRepository)
    {
        $this->documentRepository = $documentRepository;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $isLocked = $this->documentRepository->hasLock($entityDto->getInstance());

        $field->setFormattedValue($isLocked);
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return WopiDocumentLockField::class === $field->getFieldFqcn();
    }
}
