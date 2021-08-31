<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiLib\WopiInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Entity\Lock;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use loophp\psr17\Psr17Interface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use function strlen;

final class Wopi implements WopiInterface
{
    private AuditReader $auditReader;

    private DocumentRepository $documentRepository;

    private Psr17Interface $psr17;

    private RouterInterface $routerInterface;

    private Security $security;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        Psr17Interface $psr17,
        WopiDiscoveryInterface $wopiDiscovery,
        DocumentRepository $documentRepository,
        AuditReader $auditReader,
        Security $security,
        RouterInterface $routerInterface
    ) {
        $this->psr17 = $psr17;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->documentRepository = $documentRepository;
        $this->auditReader = $auditReader;
        $this->security = $security;
        $this->routerInterface = $routerInterface;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        [$documentId, $documentRevisionId] = explode('-', $fileId, 2);

        if (null === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        $revision = $this->auditReader->findRevision($documentRevisionId);
        $document = $this->auditReader->find(Document::class, $documentId, $documentRevisionId);

        /*
                if ([] === $this->wopiDiscovery->discoverExtension($document->getExtension())) {
                    return $this
                        ->psr17
                        ->createResponse(404);
                }
         */
        $user = $this->security->getUser();

        // TODO: Find first revision and get user/owner from it.
        // $revisions = $this->auditReader->findRevisions(Document::class, $documentId);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(
                [
                    'BaseFileName' => $document->getFilename(),
                    'OwnerId' => 'Symfony',
                    'Size' => (int) $document->getSize(),
                    'UserId' => null === $user ? 'anonymous' : $user->getUserIdentifier(),
                    'Version' => sprintf('v%s', $documentRevisionId),
                    'ReadOnly' => false,
                    'UserCanWrite' => true,
                    'UserCanNotWriteRelative' => false,
                    'SupportsUserInfo' => false,
                    'SupportsDeleteFile' => true,
                    'SupportsLocks' => true,
                    'SupportsGetLock' => true,
                    'SupportsExtendedLockLength' => true,
                    'UserFriendlyName' => 'User ' . $user === null ? 'anonymous' : $user->getUserIdentifier(),
                    'LastModifiedTime' => $revision->getTimestamp()->format('Y-m-d\TH:i:s.uP'),
                    'SupportsUpdate' => true,
                    'SupportsRename' => false,
                    'DisablePrint' => false,
                    'AllowExternalMarketplace' => true,
                ]
            )));
    }

    public function deleteFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->getDebugResponse(__FUNCTION__, $request);
    }

    public function enumerateAncestors(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        return $this->getDebugResponse(__FUNCTION__, $request);
    }

    public function getFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        [$documentId, $documentRevisionId] = explode('-', $fileId);

        if (null === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        $document = $this->documentRepository->find($documentId);

        $content = (null === $contentResource = $document->getContent()) ?
            $this->psr17->createStream('') :
            $this->psr17->createStreamFromResource($contentResource);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $documentRevisionId)
            )
            ->withHeader(
                'Content-Type',
                'application/octet-stream',
            )
            ->withHeader(
                'Content-Length',
                $document->getSize()
            )
            ->withHeader(
                'Content-Disposition',
                sprintf('attachment; filename=%s', $document->getFilename())
            )
            ->withBody($content);
    }

    public function getLock(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);
        $lock = $document->getLock();

        if (null === $lock) {
            return $this
                ->psr17
                ->createResponse(404)
                ->withHeader('X-WOPI-Lock', '');
        }

        if (false === $lock->isValid()) {
            return $this
                ->psr17
                ->createResponse(404);
        }

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('X-WOPI-Lock', $lock->getLock());
    }

    public function getShareUrl(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        return $this->getDebugResponse(__FUNCTION__, $request);
    }

    public function lock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        [$documentId, $documentRevisionId] = explode('-', $fileId);

        if (null === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        $document = $this->documentRepository->find($documentId);
        $lock = $document->getLock();

        if ((null !== $lock) && (false === $lock->isValid())) {
            $document->setLock(null);
            $this->documentRepository->add($document);

            $lock = $document->getLock();
        }

        if (null === $lock) {
            $document->setLock((new Lock())->setLock($xWopiLock));

            $this->documentRepository->add($document);

            return $this
                ->psr17
                ->createResponse()
                ->withHeader(
                    'X-WOPI-ItemVersion',
                    sprintf('v%s', $documentRevisionId)
                );
        }

        if ($lock->getLock() === $xWopiLock) {
            return $this->refreshLock($fileId, $accessToken, $xWopiLock, $request);
        }

        $revision = $this
            ->auditReader
            ->findRevision($this->auditReader->getCurrentRevision(Document::class, $documentId));

        return $this
            ->psr17
            ->createResponse(409)
            ->withAddedHeader('X-WOPI-Lock', $lock->getLock())
            ->withAddedHeader('X-WOPI-ItemVersion', 'v' . $revision->getRev());
    }

    public function putFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiEditors,
        RequestInterface $request
    ): ResponseInterface {
        [$documentId, $documentRevisionId] = explode('-', $fileId);

        if (null === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        $document = $this->documentRepository->find($documentId);
        $currentLock = $document->getLock();

        // File is unlocked
        if (null === $currentLock) {
            if ('0' !== $document->getSize()) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader(
                        'X-WOPI-ItemVersion',
                        sprintf('v%s', $documentRevisionId)
                    );
            }
        }

        // File is locked
        if (null !== $currentLock) {
            if ($currentLock->getLock() !== $xWopiLock) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader(
                        'X-WOPI-Lock',
                        $currentLock->getLock()
                    )
                    ->withHeader(
                        'X-WOPI-ItemVersion',
                        sprintf('v%s', $documentRevisionId)
                    );
            }
        }

        // File is unlocked.
        /*
        if (0 !== $document->getSize()) {
            return $this
                ->psr17
                ->createResponse(409);
        }
         */

        $body = (string) $request->getBody();

        $document->setContent($body);
        $document->setLock((new Lock())->setLock($xWopiLock));
        $document->setSize((string) strlen($body));

        $this->documentRepository->add($document);

        // New revision.
        $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-Lock',
                $xWopiLock
            )
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $documentRevisionId)
            );
    }

    public function putRelativeFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        $pathInfo = pathinfo($request->getHeaderLine('X-WOPI-SuggestedTarget'));

        $new = new Document();
        $new->setName($pathInfo['filename']);
        $new->setExtension($pathInfo['extension']);
        $new->setContent((string) $request->getBody());
        $new->setSize($request->getHeaderLine('content-length'));

        $this->documentRepository->add($new);
        $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $new->getId());

        $uri = $this
            ->psr17
            ->createUri(
                $this
                    ->routerInterface
                    ->generate(
                        'checkFileInfo',
                        [
                            'fileId' => sprintf('%s-%s', $new->getId(), $documentRevisionId),
                        ],
                        RouterInterface::ABSOLUTE_URL
                    )
            )
            ->withQuery(http_build_query([
                'access_token' => $accessToken,
            ]));

        $properties = [
            'Name' => $new->getFilename(),
            'Url' => (string) $uri,
        ];

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($properties)));
    }

    public function putUserInfo(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->getDebugResponse(__FUNCTION__, $request);
    }

    public function refreshLock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        $this->unlock($fileId, $accessToken, $xWopiLock, $request);

        return $this->lock($fileId, $accessToken, $xWopiLock, $request);
    }

    public function renameFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiRequestedName,
        RequestInterface $request
    ): ResponseInterface {
        return $this->getDebugResponse(__FUNCTION__, $request);
    }

    public function unlock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        [$documentId, $documentRevisionId] = explode('-', $fileId);

        if (null === $documentRevisionId) {
            $documentRevisionId = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        $document = $this->documentRepository->find($documentId);
        $currentLock = $document->getLock();

        if (null === $currentLock) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withAddedHeader('X-WOPI-Lock', '');
        }

        if ($currentLock->getLock() !== $xWopiLock) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withAddedHeader('X-WOPI-Lock', $currentLock->getLock());
        }

        $document->setLock(null);

        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse()
            ->withAddedHeader('X-WOPI-Lock', '')
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $documentRevisionId)
            );
    }

    public function unlockAndRelock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiOldLock,
        RequestInterface $request
    ): ResponseInterface {
        if (null === $xWopiOldLock) {
            return $this->lock($fileId, $accessToken, $xWopiLock, $request);
        }

        $this->unlock($fileId, $accessToken, $xWopiOldLock, $request);

        return $this->lock($fileId, $accessToken, $xWopiLock, $request);
    }

    private function getDebugResponse(string $method, RequestInterface $request): ResponseInterface
    {
        $params = [];
        parse_str($request->getUri()->getQuery(), $params);

        $data = (string) json_encode(array_merge(
            ['method' => $method],
            $params,
            $request->getHeaders()
        ));

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream($data));
    }
}
