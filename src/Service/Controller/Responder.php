<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

final class Responder implements ResponderInterface
{
    private SerializerInterface $serializer;

    private Environment $twig;

    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer
    ) {
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;
        $this->serializer = $serializer;
    }

    public function file(
        $file,
        ?string $filename = null,
        string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT
    ): BinaryFileResponse {
        $response = new BinaryFileResponse($file);

        $filename ??= $response->getFile()->getFilename();
        $response->setContentDisposition($disposition, $filename);

        return $response;
    }

    public function json(
        $data,
        int $status = 200,
        array $headers = [],
        array $context = []
    ): JsonResponse {
        return new JsonResponse(
            $this
                ->serializer
                ->serialize(
                    $data,
                    'json',
                    array_merge(
                        [
                            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
                        ],
                        $context
                    )
                ),
            $status,
            $headers,
            true
        );
    }

    public function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    public function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
        array $headers = []
    ): RedirectResponse {
        return $this->redirect($this->urlGenerator->generate($route, $parameters), $status, $headers);
    }

    public function render(string $template, array $context = [], int $status = 200, array $headers = []): Response
    {
        $response = new Response($this->twig->render($template, $context), $status, $headers);

        if (!$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        return $response;
    }
}
