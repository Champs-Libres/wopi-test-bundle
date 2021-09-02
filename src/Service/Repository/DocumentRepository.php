<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Repository;

use ChampsLibres\WopiLib\Service\Contract\DocumentLockManagerInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Psr\Http\Message\RequestInterface;
use SimpleThings\EntityAudit\AuditReader;
use SimpleThings\EntityAudit\Revision;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

final class DocumentRepository implements ObjectRepository
{
    private AuditReader $auditReader;

    private DocumentLockManagerInterface $documentLockManager;

    private EntityManagerInterface $entityManager;

    private ObjectRepository $repository;

    private RequestInterface $request;

    public function __construct(EntityManagerInterface $entityManager, AuditReader $auditReader, DocumentLockManagerInterface $documentLockManager, RequestStack $requestStack, HttpMessageFactoryInterface $httpMessageFactory)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(Document::class);
        $this->auditReader = $auditReader;
        $this->documentLockManager = $documentLockManager;
        $this->request = $httpMessageFactory->createRequest($requestStack->getCurrentRequest());
    }

    public function add(Document $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush($document);
    }

    public function deleteLock(Document $document): bool
    {
        return $this->documentLockManager->deleteLock((string) $document->getId(), $this->request);
    }

    public function find($id): ?Document
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<int, Document>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    /**
     * @param mixed|null $limit
     * @param mixed|null $offset
     *
     * @return array<int, Document>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findFromFileId(string $fileId): ?Document
    {
        if (false === strpos($fileId, '-', 0)) {
            $fileId .= '-0';
        }

        [$documentId, $documentRevisionId] = explode('-', $fileId, 2);

        if (0 === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        return $this->find($documentId);
    }

    public function findOneBy(array $criteria): ?Document
    {
        return $this->repository->findOneBy($criteria);
    }

    public function findRevisionFromFileId(string $fileId): ?Revision
    {
        if (false === strpos($fileId, '-', 0)) {
            $fileId .= '-0';
        }

        [$documentId, $documentRevisionId] = explode('-', $fileId, 2);

        if (0 === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        try {
            $revision = $this->auditReader->findRevision($documentRevisionId);
        } catch (Throwable $e) {
            return null;
        }

        return $revision;
    }

    public function getClassName(): string
    {
        return Document::class;
    }

    public function getLock(Document $document): string
    {
        return $this->documentLockManager->getLock((string) $document->getId(), $this->request);
    }

    public function hasLock(Document $document): bool
    {
        return $this->documentLockManager->hasLock((string) $document->getId(), $this->request);
    }

    public function lock(Document $document, string $lockId): bool
    {
        return $this->documentLockManager->setLock((string) $document->getId(), $lockId, $this->request);
    }

    public function remove(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush($document);
    }
}
