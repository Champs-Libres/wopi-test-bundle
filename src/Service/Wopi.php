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
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use function strlen;

final class Wopi implements WopiInterface
{
    private CacheItemPoolInterface $cache;

    private DocumentRepository $documentRepository;

    private Psr17Interface $psr17;

    private RouterInterface $routerInterface;

    private Security $security;

    public function __construct(
        DocumentRepository $documentRepository,
        Psr17Interface $psr17,
        CacheItemPoolInterface $cache,
        RouterInterface $routerInterface,
        Security $security
    ) {
        $this->documentRepository = $documentRepository;
        $this->psr17 = $psr17;
        $this->cache = $cache;
        $this->routerInterface = $routerInterface;
        $this->security = $security;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findFromFileId($fileId);
        $revision = $this->documentRepository->findLatestRevisionFromFileId($fileId);

        $user = $this->security->getUser();

        if ($document->getShare()->isEmpty()) {
            $share = new Share();
            $share->setDocument($document);
            $document->addShare($share);
            $this->documentRepository->add($document);
        }

        $userCacheKey = sprintf('wopi_putUserInfo_%s', $this->security->getUser()->getUserIdentifier());

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
                    'UserCanAttend' => true,
                    'UserCanPresent' => true,
                    'UserCanRename' => true,
                    'UserCanWrite' => true,
                    'UserCanNotWriteRelative' => false,
                    'SupportsUserInfo' => true,
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
                    'DownloadUrl' => $this->routerInterface->generate('share', ['uuid' => $document->getShare()->last()->getUuid()], RouterInterface::ABSOLUTE_URL),
                    'FileSharingUrl' => $this->routerInterface->generate('share', ['uuid' => $document->getShare()->last()->getUuid()], RouterInterface::ABSOLUTE_URL),
                    'BreadcrumbBrandName' => 'BreadcrumbBrandName',
                    'SHA256' => $document->getSha256(),
                    'UserInfo' => (string) $this->cache->getItem($userCacheKey)->get(),
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
        $revision = $this->documentRepository->findLatestRevisionFromFileId($fileId);

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
        $version = $this->documentRepository->getVersion($document);

        if ($this->documentRepository->hasLock($document)) {
            if ($xWopiLock === $currentLock = $this->documentRepository->getLock($document)) {
                return $this->refreshLock($fileId, $accessToken, $xWopiLock, $request);
            }

            return $this
                ->psr17
                ->createResponse(409)
                ->withHeader('X-WOPI-Lock', $currentLock)
                ->withHeader(
                    'X-WOPI-ItemVersion',
                    sprintf('v%s', $version)
                );
        }

        $this->documentRepository->lock($document, $xWopiLock);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $version)
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
        $version = $this->documentRepository->getVersion($document);

        // File is unlocked
        if (false === $this->documentRepository->hasLock($document)) {
            if ('0' !== $document->getSize()) {
                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withHeader(
                        'X-WOPI-ItemVersion',
                        sprintf('v%s', $version)
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
                        sprintf('v%s', $version)
                    );
            }
        }

        $body = (string) $request->getBody();

        $document->setContent($body);
        $document->setSize((string) strlen($body));

        $this->documentRepository->add($document);
        $version = $this->documentRepository->getVersion($document);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'X-WOPI-Lock',
                $xWopiLock
            )
            ->withHeader(
                'X-WOPI-ItemVersion',
                sprintf('v%s', $version)
            );
    }

    public function putRelativeFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        if ($request->hasHeader('X-WOPI-SuggestedTarget') && $request->hasHeader('X-WOPI-RelativeTarget')) {
            return $this
                ->psr17
                ->createResponse(400);
        }

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
            $overwriteRelativeTarget = $request->hasHeader('X-WOPI-OverwriteRelativeTarget') ?
                strtolower($request->getHeaderLine('X-WOPI-OverwriteRelativeTarget')):
                'false';

            $overwriteRelativeTarget = 'false' === $overwriteRelativeTarget ? false : true;

            $relativeTarget = $request->getHeaderLine('X-WOPI-RelativeTarget');

            $relativeTargetPathInfo = pathinfo($relativeTarget);

            /** @var null|Document $document */
            $document = $this->documentRepository->findOneBy([
                'name' => $relativeTargetPathInfo['filename'],
                'extension' => $relativeTargetPathInfo['extension'],
            ]);

            /**
             * If a file with the specified name already exists,
             * the host must respond with a 409 Conflict,
             * unless the X-WOPI-OverwriteRelativeTarget request header is set to true.
             *
             * When responding with a 409 Conflict for this reason,
             * the host may include an X-WOPI-ValidRelativeTarget specifying a file name that is valid.
             *
             * If the X-WOPI-OverwriteRelativeTarget request header is set to true
             * and a file with the specified name already exists and is locked,
             * the host must respond with a 409 Conflict and include an
             * X-WOPI-Lock response header containing the value of the current lock on the file.
             */
            if (null !== $document) {
                if (false === $overwriteRelativeTarget ) {
                    return $this
                        ->psr17
                        ->createResponse(409)
                        ->withHeader('Content-Type', 'application/json')
                        ->withHeader('X-WOPI-ValidRelativeTarget', sprintf('%s.%s', uniqid(), $relativeTargetPathInfo['extension']));
                }

                if ($this->documentRepository->hasLock($document)) {
                    return $this
                        ->psr17
                        ->createResponse(409)
                        ->withHeader('X-WOPI-Lock', $this->documentRepository->getLock($document));
                }
            }

            $target = $relativeTarget;
        }

        $pathInfo = pathinfo($target);

        $new = new Document();
        $new->setName($pathInfo['filename']);
        $new->setExtension($pathInfo['extension']);
        $new->setContent((string) $request->getBody());
        $new->setSize($request->getHeaderLine('X-WOPI-Size'));

        $this->documentRepository->add($new);

        $uri = $this
            ->psr17
            ->createUri(
                $this
                    ->routerInterface
                    ->generate(
                        'checkFileInfo',
                        [
                            'fileId' => $new->getUuid(),
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
        $userCacheKey = sprintf('wopi_putUserInfo_%s', $this->security->getUser()->getUserIdentifier());

        $cacheItem = $this->cache->getItem($userCacheKey);
        $cacheItem->set((string) $request->getBody());
        $this->cache->save($cacheItem);

        return $this
            ->psr17
            ->createResponse();
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
        $version = $this->documentRepository->getVersion($document);

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
                sprintf('v%s', $version)
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
