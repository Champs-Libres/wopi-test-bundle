<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiBundle\Service\Uri;
use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiLib\WopiInterface;
use loophp\psr17\Psr17Interface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

final class Wopi implements WopiInterface
{
    private string $filesRepository;

    private Filesystem $fs;

    private KernelInterface $kernel;

    private Psr17Interface $psr17;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(Psr17Interface $psr17, KernelInterface $kernel, WopiDiscoveryInterface $wopiDiscovery)
    {
        $this->kernel = $kernel;
        $this->psr17 = $psr17;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->fs = new Filesystem();
        $this->filesRepository = sprintf('%s/files', $this->kernel->getCacheDir());
        $this->fs->mkdir($this->filesRepository);
    }

    public function checkFileInfo(
        string $fileId,
        ?string $accessToken,
        RequestInterface $request
    ): ResponseInterface {
        $filepath = sprintf(
            '%s/%s',
            $this->filesRepository,
            $fileId
        );

        if (!$this->fs->exists($filepath)) {
            $this->fs->touch($filepath);
        }

        $filepathInfo = pathinfo($filepath);

        if (false !== current($this->wopiDiscovery->discoverExtension($filepathInfo['extension']))) {
            // TODO Exception.
        }

        $data = [
            'BaseFileName' => $filepathInfo['basename'],
            'OwnerId' => uniqid(),
            'Size' => filesize($filepath),
            'UserId' => uniqid(),
            'Version' => 'v' . uniqid(),
            'ReadOnly' => false,
            'UserCanWrite' => true,
            'UserCanNotWriteRelative' => true,
            'SupportsLocks' => true,
            'UserFriendlyName' => 'User Name ' . uniqid(),
            'UserExtraInfo' => [],
            'LastModifiedTime' => filemtime($filepath),
            'CloseButtonClosesWindow' => true,
        ];

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($data)));
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
        $filepath = sprintf(
            '%s/%s',
            $this->filesRepository,
            $fileId
        );

        return $this
            ->psr17
            ->createResponse()
            ->withHeader(
                'Content-Type',
                'application/octet-stream',
            )
            ->withHeader(
                'Content-Disposition',
                sprintf('attachment; filename=%s', basename($filepath))
            )
            ->withBody($this->psr17->createStreamFromFile($filepath));
    }

    public function getLock(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        $lockFilepath = sprintf(
            '%s/%s.lock',
            $this->filesRepository,
            $fileId
        );

        $lock = $this->fs->exists($lockFilepath) ?
            basename($lockFilepath) :
            '';

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('X-WOPI-Lock', $lock);
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
        $lockFilepath = sprintf(
            '%s/%s.lock',
            $this->filesRepository,
            $fileId
        );

        $this->fs->touch($lockFilepath);

        return $this
            ->psr17
            ->createResponse()
            ->withAddedHeader('X-WOPI-Lock', basename($lockFilepath));
    }

    public function putFile(
        string $fileId,
        ?string $accessToken,
        string $xWopiLock,
        string $xWopiEditors,
        RequestInterface $request
    ): ResponseInterface {
        $filepath = sprintf(
            '%s/%s',
            $this->filesRepository,
            $fileId
        );

        $return = file_put_contents(
            $filepath,
            (string) $request->getBody()
        );

        if (false === $return) {
            return $this
                ->psr17
                ->createResponse(500);
        }

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('Content-Type', 'application/json')
            ->withAddedHeader('X-WOPI-Lock', sprintf('%s.lock', $filepath))
            ->withBody($this->psr17->createStream((string) json_encode([])));
    }

    public function putRelativeFile(string $fileId, ?string $accessToken, RequestInterface $request): ResponseInterface
    {
        return $this->getDebugResponse(__FUNCTION__, $request);
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
        $lockFilepath = sprintf(
            '%s/%s.lock',
            $this->filesRepository,
            $fileId
        );

        if (basename($lockFilepath) !== $xWopiLock) {
            return $this
                ->psr17
                ->createResponse(409)
                ->withAddedHeader('X-WOPI-Lock', basename($lockFilepath));
        }

        $this->fs->remove($lockFilepath);

        return $this
            ->psr17
            ->createResponse();
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
        $data = (string) json_encode(array_merge(
            ['method' => $method],
            Uri::getParams($request->getUri()),
            $request->getHeaders()
        ));

        return $this
            ->psr17
            ->createResponse()
            ->withHeader('content', 'application/json')
            ->withBody($this->psr17->createStream($data));
    }
}
