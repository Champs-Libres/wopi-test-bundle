<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Entity\Document;
use ChampsLibres\WopiLib\Contract\Service\DocumentLockManagerInterface;
use ChampsLibres\WopiLib\Contract\Service\DocumentManagerInterface;
use ChampsLibres\WopiTestBundle\Entity\Document as EntityDocument;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\RequestInterface;
use SimpleThings\EntityAudit\AuditReader;
use SimpleThings\EntityAudit\Revision;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Throwable;

final class DocumentManager implements DocumentManagerInterface
{
    private AuditReader $auditReader;

    private DocumentLockManagerInterface $documentLockManager;

    private DocumentRepository $documentRepository;

    private EntityManagerInterface $entityManager;

    private RequestInterface $request;

    public function __construct(
        AuditReader $auditReader,
        DocumentLockManagerInterface $documentLockManager,
        DocumentRepository $documentRepository,
        EntityManagerInterface $entityManager,
        HttpMessageFactoryInterface $httpMessageFactory,
        RequestStack $requestStack
    ) {
        $this->auditReader = $auditReader;
        $this->documentLockManager = $documentLockManager;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
        $this->request = $httpMessageFactory->createRequest($requestStack->getCurrentRequest());
    }

    public function create(array $data): Document
    {
        $document = (new ObjectNormalizer())->denormalize([], EntityDocument::class);

        $document->setName($data['name']);
        $document->setExtension($data['extension']);
        $document->setContent($data['content']);
        $document->setSize($data['size']);

        return $document;
    }

    public function deleteLock(Document $document): void
    {
        $this->documentLockManager->deleteLock($document, $this->request);
    }

    public function findByDocumentFilename(string $documentFilename): ?Document
    {
        $pathInfo = pathinfo($documentFilename);

        return $this
            ->documentRepository
            ->findOneBy([
                'name' => $pathInfo['filename'],
                'extension' => $pathInfo['extension'],
            ]);
    }

    public function findByDocumentId(string $documentId): ?Document
    {
        return $this->documentRepository->findOneBy(['uuid' => $documentId]);
    }

    public function getLock(Document $document): string
    {
        return $this->documentLockManager->getLock($document, $this->request);
    }

    public function getVersion(Document $document): string
    {
        return (string) $this->findLatestRevisionFromFileId((string) $document->getWopiFileId())->getRev();
    }

    public function hasLock(Document $document): bool
    {
        return $this->documentLockManager->hasLock($document, $this->request);
    }

    public function lock(Document $document, string $lockId): void
    {
        $this->documentLockManager->setLock($document, $lockId, $this->request);
    }

    public function remove(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush($document);
    }

    public function write(Document $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush($document);
    }

    private function findLatestRevisionFromFileId(string $fileId): ?Revision
    {
        try {
            $revision = $this->auditReader->findRevision($this->auditReader->getCurrentRevision(EntityDocument::class, $this->documentRepository->findOneBy(['uuid' => $fileId])->getId()));
        } catch (Throwable $e) {
            return null;
        }

        return $revision;
    }
}
