<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Service\DocumentManagerInterface;
use ChampsLibres\WopiLib\Contract\Service\WopiInterface;
use ChampsLibres\WopiTestBundle\Controller\Admin\DashboardController;
use ChampsLibres\WopiTestBundle\Controller\Admin\DocumentCrudController;
use ChampsLibres\WopiTestBundle\Entity\Share;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use loophp\psr17\Psr17Interface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Component\Routing\RouterInterface;

final class Wopi implements WopiInterface
{
    private AdminUrlGenerator $adminUrlGenerator;

    private AuditReader $auditReader;

    private DocumentManagerInterface $documentManager;

    private Psr17Interface $psr17;

    private RouterInterface $router;

    private WopiInterface $wopi;

    public function __construct(
        AdminUrlGenerator $adminUrlGenerator,
        AuditReader $auditReader,
        DocumentManagerInterface $documentManager,
        Psr17Interface $psr17,
        RouterInterface $router,
        WopiInterface $wopi
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->auditReader = $auditReader;
        $this->documentManager = $documentManager;
        $this->psr17 = $psr17;
        $this->router = $router;
        $this->wopi = $wopi;
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $response = $this->wopi->checkFileInfo($fileId, $accessToken, $request);

        $document = $this->documentManager->findByDocumentId($fileId);
        $revision = $this->auditReader->findRevision($this->documentManager->getVersion($document));

        $body = json_decode((string) $response->getBody(), true);

        if ($document->getShare()->isEmpty()) {
            $share = new Share();
            $share->setDocument($document);
            $document->addShare($share);
            $this->documentManager->write($document);
        }

        return $response
            ->withBody(
                $this
                    ->psr17
                    ->createStream(
                        (string) json_encode(
                            $body +
                            [
                                'Version' => sprintf('v%s', $revision->getRev()),
                                'LastModifiedTime' => $revision->getTimestamp()->format('Y-m-d\TH:i:s.uP'),
                                'DownloadUrl' => $this->router->generate('share', ['uuid' => $document->getShare()->last()->getUuid()], RouterInterface::ABSOLUTE_URL),
                                'FileSharingUrl' => $this->router->generate('share', ['uuid' => $document->getShare()->last()->getUuid()], RouterInterface::ABSOLUTE_URL),
                                'SHA256' => $document->getSha256(),
                            ]
                        )
                    )
            );
    }

    public function deleteFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->wopi->deleteFile($fileId, $accessToken, $request);
    }

    public function enumerateAncestors(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->enumerateAncestors($fileId, $accessToken, $request);
    }

    public function getFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->wopi->getFile($fileId, $accessToken, $request);
    }

    public function getLock(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->wopi->getLock($fileId, $accessToken, $request);
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
        return $this->wopi->lock($fileId, $accessToken, $xWopiLock, $request);
    }

    public function putFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiEditors,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->putFile($fileId, $accessToken, $xWopiLock, $xWopiEditors, $request);
    }

    public function putRelativeFile(string $fileId, string $accessToken, ?string $suggestedTarget, ?string $relativeTarget, bool $overwriteRelativeTarget, int $size, RequestInterface $request): ResponseInterface
    {
        $response = $this->wopi->putRelativeFile($fileId, $accessToken, $suggestedTarget, $relativeTarget, $overwriteRelativeTarget, $size, $request);

        if (200 !== $response->getStatusCode()) {
            return $response;
        }

        $properties = json_decode((string) $response->getBody(), true);

        $properties = [
            'HostEditUrl' => $this
                ->adminUrlGenerator
                ->setDashboard(DashboardController::class)
                ->setAction('edit')
                ->setController(DocumentCrudController::class)
                ->setEntityId($properties['HostEditUrl'])
                ->generateUrl(),
            'HostViewUrl' => $this
                ->adminUrlGenerator
                ->setDashboard(DashboardController::class)
                ->setAction('detail')
                ->setController(DocumentCrudController::class)
                ->setEntityId($properties['HostViewUrl'])
                ->generateUrl(),
        ] + $properties;

        return $response
            ->withBody($this->psr17->createStream((string) json_encode($properties)));
    }

    public function putUserInfo(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->wopi->putUserInfo($fileId, $accessToken, $request);
    }

    public function refreshLock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->refreshLock($fileId, $accessToken, $xWopiLock, $request);
    }

    public function renameFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiRequestedName,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->renameFile($fileId, $accessToken, $xWopiLock, $xWopiRequestedName, $request);
    }

    public function unlock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->unlock($fileId, $accessToken, $xWopiLock, $request);
    }

    public function unlockAndRelock(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiOldLock,
        RequestInterface $request
    ): ResponseInterface {
        return $this->wopi->unlockAndRelock($fileId, $accessToken, $xWopiLock, $xWopiOldLock, $request);
    }
}
