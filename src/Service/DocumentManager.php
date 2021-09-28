<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Entity\Document as WopiDocument;
use ChampsLibres\WopiLib\Contract\Service\DocumentLockManagerInterface;
use ChampsLibres\WopiLib\Contract\Service\DocumentManagerInterface;
use ChampsLibres\WopiTestBundle\Entity\Document as EntityDocument;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use loophp\psr17\Psr17Interface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use SimpleThings\EntityAudit\AuditReader;
use SimpleThings\EntityAudit\Revision;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Throwable;

use function array_key_exists;
use function strlen;

final class DocumentManager implements DocumentManagerInterface
{
    private AuditReader $auditReader;

    private DocumentLockManagerInterface $documentLockManager;

    private DocumentRepository $documentRepository;

    private EntityManagerInterface $entityManager;

    private Psr17Interface $psr17;

    private RequestInterface $request;

    public function __construct(
        AuditReader $auditReader,
        DocumentLockManagerInterface $documentLockManager,
        DocumentRepository $documentRepository,
        EntityManagerInterface $entityManager,
        HttpMessageFactoryInterface $httpMessageFactory,
        RequestStack $requestStack,
        Psr17Interface $psr17
    ) {
        $this->auditReader = $auditReader;
        $this->documentLockManager = $documentLockManager;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
        $this->request = $httpMessageFactory->createRequest($requestStack->getCurrentRequest());
        $this->psr17 = $psr17;
    }

    public function create(array $data): WopiDocument
    {
        $document = (new ObjectNormalizer())->denormalize([], EntityDocument::class);

        $document->setName($data['name']);
        $document->setExtension($data['extension']);
        $document->setContent($data['content']);
        $document->setSize($data['size']);

        return $document;
    }

    /**
     * @param EntityDocument $document
     */
    public function deleteLock(WopiDocument $document): void
    {
        $this->documentLockManager->deleteLock($document, $this->request);
    }

    public function findByDocumentFilename(string $documentFilename): ?WopiDocument
    {
        $pathInfo = pathinfo($documentFilename);

        return $this
            ->documentRepository
            ->findOneBy([
                'name' => $pathInfo['filename'],
                'extension' => $pathInfo['extension'],
            ]);
    }

    public function findByDocumentId(string $documentId): ?WopiDocument
    {
        return $this->documentRepository->findOneBy(['uuid' => $documentId]);
    }

    /**
     * @param EntityDocument $document
     */
    public function getBasename(WopiDocument $document): string
    {
        return $document->getBasename();
    }

    /**
     * @param EntityDocument $document
     */
    public function getDocumentId(WopiDocument $document): string
    {
        return $document->getWopiDocId();
    }

    public function getLock(WopiDocument $document): string
    {
        return $this->documentLockManager->getLock($document, $this->request);
    }

    /**
     * @param EntityDocument $document
     */
    public function getSha256(WopiDocument $document): string
    {
        return base64_encode(hash('sha256', (string) $document->getContent()));
    }

    /**
     * @param EntityDocument $document
     */
    public function getSize(WopiDocument $document): int
    {
        return (null === $content = $document->getContent()) ? 0 : strlen(stream_get_contents($content));
    }

    /**
     * @param EntityDocument $document
     */
    public function getVersion(WopiDocument $document): string
    {
        return (string) $this->findLatestRevisionFromFileId((string) $this->getDocumentId($document))->getRev();
    }

    public function hasLock(WopiDocument $document): bool
    {
        return $this->documentLockManager->hasLock($document, $this->request);
    }

    public function lock(WopiDocument $document, string $lockId): void
    {
        $this->documentLockManager->setLock($document, $lockId, $this->request);
    }

    /**
     * @param EntityDocument $document
     */
    public function read(WopiDocument $document): StreamInterface
    {
        return (null === $contentResource = $document->getContent()) ?
            $this->psr17->createStream('') :
            $this->psr17->createStreamFromResource($contentResource);
    }

    public function remove(WopiDocument $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush($document);
    }

    /**
     * @param EntityDocument $document
     */
    public function write(WopiDocument $document, array $properties = []): void
    {
        if (array_key_exists('content', $properties)) {
            $document->setContent($properties['content']);
        }

        if (array_key_exists('filename', $properties)) {
            $document->setFilename($properties['filename']);
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush($document);
    }

    private function findLatestRevisionFromFileId(string $fileId): ?Revision
    {
        try {
            $revision = $this
                ->auditReader
                ->findRevision(
                    $this
                        ->auditReader
                        ->getCurrentRevision(
                            EntityDocument::class,
                            $this->documentRepository->findOneBy(['uuid' => $fileId])->getId()
                        )
                );
        } catch (Throwable $e) {
            return null;
        }

        return $revision;
    }
}
