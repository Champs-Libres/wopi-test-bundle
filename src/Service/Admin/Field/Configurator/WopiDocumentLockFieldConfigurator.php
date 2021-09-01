<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Admin\Field\Configurator;

use ChampsLibres\WopiLib\Service\DocumentLockManager;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentLockField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class WopiDocumentLockFieldConfigurator implements FieldConfiguratorInterface
{
    private DocumentLockManager $documentLockManager;

    private HttpMessageFactoryInterface $httpMessageFactory;

    private RequestStack $requestStack;

    public function __construct(DocumentLockManager $documentLockManager, HttpMessageFactoryInterface $httpMessageFactory, RequestStack $requestStack)
    {
        $this->documentLockManager = $documentLockManager;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->requestStack = $requestStack;
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $isLocked = $this->documentLockManager->hasLock((string) $field->getValue(), $this->httpMessageFactory->createRequest($this->requestStack->getCurrentRequest()));

        $field->setFormattedValue($isLocked);
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return WopiDocumentLockField::class === $field->getFieldFqcn();
    }
}
