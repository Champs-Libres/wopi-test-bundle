<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiLib\Service\Contract\DocumentLockManagerInterface;
use ChampsLibres\WopiLib\Service\Contract\WopiInterface;
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

    private DocumentLockManagerInterface $documentLockManager;

    private DocumentRepository $documentRepository;

    private Psr17Interface $psr17;

    private RouterInterface $routerInterface;

    private Security $security;

    public function __construct(
        Psr17Interface $psr17,
        WopiDiscoveryInterface $wopiDiscovery,
        DocumentRepository $documentRepository,
        AuditReader $auditReader,
        Security $security,
        RouterInterface $routerInterface,
        DocumentLockManagerInterface $documentLockManager
    ) {
        $this->psr17 = $psr17;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->documentRepository = $documentRepository;
        $this->auditReader = $auditReader;
        $this->security = $security;
        $this->routerInterface = $routerInterface;
        $this->documentLockManager = $documentLockManager;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        /*
                if ([] === $this->wopiDiscovery->discoverExtension($document->getExtension())) {
                    return $this
                        ->psr17
                        ->createResponse(404);
                }
         */

        $user = $this->security->getUser();

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
                    'Version' => sprintf('v%s', $revision->getRev()),
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
        $document = $this->documentRepository->findFromFileId($fileId);

        if ($this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            return $this
                ->psr17
                ->createResponse(409);
        }

        $this->documentRepository->remove($document);

        return $this
            ->psr17
            ->createResponse(200);
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
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        $content = (null === $contentResource = $document->getContent()) ?
            $this->psr17->createStream('') :
            $this->psr17->createStreamFromResource($contentResource);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $revision->getRev())
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
        $document = $this->documentRepository->findFromFileId($fileId);

        if ($this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            return $this
                ->psr17
                ->createResponse()
                ->withHeader('X-WOPI-Lock', $this->documentLockManager->getLock((string) $document->getId(), $request));
        }

        return $this
            ->psr17
            ->createResponse(404)
            ->withHeader('X-WOPI-Lock', '');
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
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        if ($this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            if ($xWopiLock === $currentLock = $this->documentLockManager->getLock((string) $document->getId(), $request)) {
                return $this->refreshLock($fileId, $accessToken, $xWopiLock, $request);
            }

            $revision = $this
                ->auditReader
                ->findRevision($this->auditReader->getCurrentRevision(Document::class, $document->getId()));

            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', $currentLock)
                ->withHeader('X-WOPI-ItemVersion', 'v' . $revision->getRev());
        }

        $this->documentLockManager->setLock((string) $document->getId(), $xWopiLock, $request);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $revision->getRev())
            );
    }

    public function putFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiEditors,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        // File is unlocked
        if (false === $this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            if ('0' !== $document->getSize()) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader(
                        'X-WOPI-ItemVersion',
                        sprintf('v%s', $revision->getRev())
                    );
            }
        }

        // File is locked
        if ($this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            if ($xWopiLock !== $currentLock = $this->documentLockManager->getLock((string) $document->getId(), $request)) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader(
                        'X-WOPI-Lock',
                        $currentLock
                    )
                    ->withHeader(
                        'X-WOPI-ItemVersion',
                        sprintf('v%s', $revision->getRev())
                    );
            }
        }

        $body = (string) $request->getBody();

        $document->setContent($body);
        $document->setSize((string) strlen($body));

        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-Lock',
                $xWopiLock
            )
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $this->auditReader->getCurrentRevision(Document::class, $document->getId()))
            );
    }

    public function putRelativeFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        if ($request->hasHeader('X-WOPI-SuggestedTarget')) {
            $suggestedTarget = $request->getHeaderLine('X-WOPI-SuggestedTarget');

            // If it starts with a dot...
            if (0 === strpos($suggestedTarget, '.', 0)) {
                $document = $this->documentRepository->findFromFileId($fileId);
                $suggestedTarget = sprintf('%s%s', $document->getName(), $suggestedTarget);
            }

            $target = $suggestedTarget;
        }

        if ($request->hasHeader('X-WOPI-RelativeTarget')) {
            $overwriteRelativeTarget = 'false';

            if ($request->hasHeader('X-WOPI-OverwriteRelativeTarget')) {
                $overwriteRelativeTarget = $request->getHeaderLine('X-WOPI-OverwriteRelativeTarget');
            }
            $overwriteRelativeTarget = 'false' === $overwriteRelativeTarget ? false : true;

            $relativeTarget = $request->getHeaderLine('X-WOPI-RelativeTarget');

            // @ TODO does not work yet.
            if (true === $overwriteRelativeTarget) {
                $relativeTargetPathInfo = pathinfo($relativeTarget);

                $document = $this->documentRepository->findOneBy([
                    'name' => $relativeTargetPathInfo['filename'],
                    'extension' => $relativeTargetPathInfo['extension'],
                ]);

                if (null !== $document) {
                    return $this
                        ->psr17
                        ->createResponse(409)
                        ->withHeader('Content-Type', 'application/json')
                        ->withHeader('X-WOPI-ValidRelativeTarget', sprintf('%s.%s', uniqid(), $relativeTargetPathInfo['extension']));
                }
            }

            $target = $relativeTarget;
        }

        $pathInfo = pathinfo($target);

        $new = new Document();
        $new->setName($pathInfo['filename']);
        $new->setExtension($pathInfo['extension']);
        $new->setContent((string) $request->getBody());
        $new->setSize($request->getHeaderLine('content-length'));

        $this->documentRepository->add($new);

        $uri = $this
            ->psr17
            ->createUri(
                $this
                    ->routerInterface
                    ->generate(
                        'checkFileInfo',
                        [
                            'fileId' => sprintf('%s-%s', $new->getId(), $this->auditReader->getCurrentRevision(Document::class, $new->getId())),
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
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        if (!$this->documentLockManager->hasLock((string) $document->getId(), $request)) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', '');
        }

        $currentLock = $this->documentLockManager->getLock((string) $document->getId(), $request);

        if ($currentLock !== $xWopiLock) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', $currentLock);
        }

        $this->documentLockManager->deleteLock((string) $document->getId(), $request);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('X-WOPI-Lock', '')
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $revision->getRev())
            );
    }

    public function unlockAndRelock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiOldLock,
        RequestInterface $request
    ): ResponseInterface {
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
