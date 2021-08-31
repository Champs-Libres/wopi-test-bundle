<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Twig;

use Symfony\Component\HttpKernel\UriSigner as HttpKernelUriSigner;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UriSigner extends AbstractExtension
{
    private HttpKernelUriSigner $uriSigner;

    public function __construct(HttpKernelUriSigner $uriSigner)
    {
        $this->uriSigner = $uriSigner;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('sign_url', [$this, 'signUrl'], ['is_safe' => ['html']]),
        ];
    }

    public function signUrl(string $url): string
    {
        return $this->uriSigner->sign($url);
    }
}
