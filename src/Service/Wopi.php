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
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use loophp\psr17\Psr17Interface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimpleThings\EntityAudit\AuditReader;

final class Wopi implements WopiInterface
{
    private AuditReader $auditReader;

    private DocumentRepository $documentRepository;

    private Psr17Interface $psr17;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        Psr17Interface $psr17,
        WopiDiscoveryInterface $wopiDiscovery,
        DocumentRepository $documentRepository,
        AuditReader $auditReader
    ) {
        $this->psr17 = $psr17;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->documentRepository = $documentRepository;
        $this->auditReader = $auditReader;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);
        $revision = $this->auditReader->findRevision($this->auditReader->getCurrentRevision(Document::class, $fileId));

        if ([] === $this->wopiDiscovery->discoverExtension($document->getExtension())) {
            // TODO Exception.
        }

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode(
                [
                    'BaseFileName' => $document->getFilename(),
                    'OwnerId' => uniqid(),
                    'Size' => 0,
                    'UserId' => uniqid(),
                    'Version' => 'v' . $revision->getRev(),
                    'ReadOnly' => false,
                    'UserCanWrite' => true,
                    'UserCanNotWriteRelative' => true,
                    'SupportsLocks' => true,
                    'UserFriendlyName' => 'User Name ' . uniqid(),
                    'UserExtraInfo' => [],
                    'LastModifiedTime' => date('Y-m-d\TH:i:s.u\Z', $revision->getTimestamp()->getTimestamp()),
                    'CloseButtonClosesWindow' => false,
                    'EnableInsertRemoteImage' => true,
                    'EnableShare' => false,
                    'SupportsUpdate' => true,
                    'SupportsRename' => false,
                    'DisablePrint' => false,
                    'DisableExport' => false,
                    'DisableCopy' => false,
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
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        $content = (null === $contentResource = $document->getContent()) ?
            $this->psr17->createStream('') :
            $this->psr17->createStreamFromResource($contentResource);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'Content-Type',
                'application/octet-stream',
            )
            ->withHeader(
                'Content-Disposition',
                sprintf('attachment; filename=%s', $document->getName())
            )
            ->withBody($content);
    }

    public function getLock(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('X-WOPI-Lock', $document->getLock());
    }

    public function getShareUrl(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $data = [
            'ShareUrl' => 'TODO',
        ];

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($data)));
    }

    public function lock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        if (null !== $currentLock = $document->getLock()) {
            if ($currentLock !== $xWopiLock) {
                $revision = $this->auditReader->findRevision($this->auditReader->getCurrentRevision(Document::class, $fileId));

                return $this
                    ->psr17
                    ->createResponse(409)
                    ->withAddedHeader('X-WOPI-Lock', $currentLock)
                    ->withAddedHeader('X-WOPI-ItemVersion', 'v' . $revision->getRev());
            }
        }

        $document->setLock($xWopiLock);

        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json');
    }

    public function putFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiEditors,
        RequestInterface $request
    ): ResponseInterface {
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        $document->setContent((string) $request->getBody());
        $document->setLock($xWopiLock);
        $document->setSize((int) $request->getHeaderLine('content-length'));

        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withAddedHeader('X-WOPI-Lock', $xWopiLock)
            ->withBody($this->psr17->createStream((string) json_encode([])));
    }

    public function putRelativeFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
//        $document = $this->documentRepository->findOneBy(['id' => $fileId]);
        $pathInfo = pathinfo($request->getHeaderLine('X-WOPI-SuggestedTarget'));

        $new = new Document();
        $new->setName($pathInfo['filename']);
        $new->setExtension($pathInfo['extension']);
        $new->setContent((string) $request->getBody());
        $new->setSize((int) $request->getHeaderLine('content-length'));

        $this->documentRepository->add($new);

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json');
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
        return $this->getDebugResponse(__FUNCTION__, $request);
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
        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        $document->setLock(null);

        $this->documentRepository->add($document);

        return $this
            ->psr17
            ->createResponse()
            ->withAddedHeader('X-WOPI-Lock', '');
    }

    public function unlockAndRelock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiOldLock,
        RequestInterface $request
    ): ResponseInterface {
        return $this->getDebugResponse(__FUNCTION__, $request);
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
            ->withHeader('content', 'application/json')
            ->withBody($this->psr17->createStream($data));
    }
}
