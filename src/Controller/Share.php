<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller;

use ChampsLibres\WopiTestBundle\Entity\Share as EntityShare;
use ChampsLibres\WopiTestBundle\Service\Repository\ShareRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

final class Share
{
    private ShareRepository $shareRepository;

    public function __construct(ShareRepository $shareRepository)
    {
        $this->shareRepository = $shareRepository;
    }

    /**
     * @Route("/share/{uuid}", name="share")
     */
    public function __invoke(string $uuid): Response
    {
        /** @var EntityShare $share */
        $share = $this->shareRepository->findBy(['uuid' => $uuid]);

        if ([] === $share) {
            return new Response('', 404);
        }

        $document = reset($share)->getDocument();
        $stream = $document->getContent();

        return new StreamedResponse(static function () use ($stream) {
            fpassthru($stream);

            exit();
        }, 200, [
            'Content-Transfer-Encoding', 'binary',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $document->getFilename()),
            'Content-Length' => fstat($stream)['size'],
        ]);
    }
}
