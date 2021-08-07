<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Controller;

use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Error\Error as TwigError;

interface ResponderInterface
{
    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     *
     * @param SplFileInfo|string $file
     */
    public function file(
        $file,
        ?string $filename = null,
        string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT
    ): BinaryFileResponse;

    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     *
     * @param array<string, list<string>|string> $headers
     * @param array<string, mixed> $context
     */
    public function json(
        mixed $data,
        int $status = 200,
        array $headers = [],
        array $context = []
    ): JsonResponse;

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param array<string, list<string>|string> $headers
     */
    public function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse;

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @param array<array-key, scalar> $parameters
     * @param array<string, list<string>> $headers
     */
    public function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
        array $headers = []
    ): RedirectResponse;

    /**
     * Render the given twig template and return an HTML response.
     *
     * @param array<string, list<string>|string> $headers
     *
     * @throws TwigError
     */
    public function render(string $template, array $context = [], int $status = 200, array $headers = []): Response;
}
