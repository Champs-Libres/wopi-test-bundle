<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Service\Contract\WopiInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Entity\Share;
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

    public function __construct(
        AuditReader $auditReader,
        DocumentRepository $documentRepository,
        Psr17Interface $psr17,
        RouterInterface $routerInterface,
        Security $security
    ) {
        $this->auditReader = $auditReader;
        $this->documentRepository = $documentRepository;
        $this->psr17 = $psr17;
        $this->routerInterface = $routerInterface;
        $this->security = $security;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);
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
                    'UserId' => $user->getUserIdentifier(),
                    'Version' => sprintf('v%s', $revision->getRev()),
                    'ReadOnly' => false,
                    'UserCanWrite' => true,
                    'UserCanNotWriteRelative' => false,
                    'SupportsUserInfo' => false,
                    'SupportsDeleteFile' => true,
                    'SupportsLocks' => true,
                    'SupportsGetLock' => true,
                    'SupportsExtendedLockLength' => true,
                    'UserFriendlyName' => sprintf('User %s', $user->getUserIdentifier()),
                    'LastModifiedTime' => $revision->getTimestamp()->format('Y-m-d\TH:i:s.uP'),
                    'SupportsUpdate' => true,
                    'SupportsRename' => true,
                    'DisablePrint' => false,
                    'AllowExternalMarketplace' => true,
                    'SupportedShareUrlTypes' => [
                        'ReadOnly',
                    ],
                ]
            )));
    }

    public function deleteFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        $document = $this->documentRepository->findFromFileId($fileId);

        if ($this->documentRepository->hasLock($document)) {
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

        if ($this->documentRepository->hasLock($document)) {
            return $this
                ->psr17
                ->createResponse()
                ->withHeader('X-WOPI-Lock', $this->documentRepository->getLock($document));
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
        $document = $this->documentRepository->findFromFileId($fileId);

        if (null !== $share = $document->getShare()->current()) {
            /** @var Share $share */
            $properties = [
                'ShareUrl' => $this->routerInterface->generate('share', ['uuid' => $share->getUuid()], RouterInterface::ABSOLUTE_URL),
            ];

            return $this
                ->psr17
                ->createResponse()
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->psr17->createStream((string) json_encode($properties)));
        }

        return $this
            ->psr17
            ->createResponse(501);
    }

    public function lock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        if ($this->documentRepository->hasLock($document)) {
            if ($xWopiLock === $currentLock = $this->documentRepository->getLock($document)) {
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

        $this->documentRepository->lock($document, $xWopiLock);

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
        if (false === $this->documentRepository->hasLock($document)) {
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
        if ($this->documentRepository->hasLock($document)) {
            if ($xWopiLock !== $currentLock = $this->documentRepository->getLock($document)) {
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
        $document = $this->documentRepository->findFromFileId($fileId);

        if ($this->documentRepository->hasLock($document)) {
            if ($xWopiLock !== $currentLock = $this->documentRepository->getLock($document)) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader('X-WOPI-Lock', $currentLock);
            }
        }

        $document->setName($xWopiRequestedName);
        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse(200);
    }

    public function unlock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findRevisionFromFileId($fileId);

        if (!$this->documentRepository->hasLock($document)) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', '');
        }

        $currentLock = $this->documentRepository->getLock($document);

        if ($currentLock !== $xWopiLock) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', $currentLock);
        }

        $this->documentRepository->deleteLock($document);

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
