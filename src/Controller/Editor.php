<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;
use loophp\psr17\Psr17Interface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class Editor
{
    private ParameterBagInterface $parameterBag;

    private Psr17Interface $psr17;

    private ResponderInterface $responder;

    private UrlGeneratorInterface $router;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        ResponderInterface $responder,
        WopiDiscoveryInterface $wopiDiscovery,
        UrlGeneratorInterface $router,
        ParameterBagInterface $parameterBag,
        Psr17Interface $psr17
    ) {
        $this->responder = $responder;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->parameterBag = $parameterBag;
        $this->router = $router;
        $this->psr17 = $psr17;
    }

    public function __invoke(string $fileName, string $extension): Response
    {
        $configuration = $this->parameterBag->get('wopi');
        $discoverExtension = current($this->wopiDiscovery->discoverExtension($extension));

        if (false === $discoverExtension) {
            $extension = 'odt';
            $discoverExtension = current($this->wopiDiscovery->discoverExtension($extension));
        }

        $configuration['server'] = $this
            ->psr17
            ->createUri($discoverExtension['urlsrc'])
            ->withQuery(
                http_build_query(
                    [
                        'WOPISrc' => $this
                            ->router
                            ->generate(
                                'checkFileInfo',
                                [
                                    'fileId' => $fileName,
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),
                    ]
                )
            );

        $configuration['favIconUrl'] = $discoverExtension['favIconUrl'];

        return $this
            ->responder
            ->render(
                '@Wopi/Editor/page.html.twig',
                $configuration
            );
    }

    public function generateRandomFilename(): Response
    {
        return $this
            ->responder
            ->redirectToRoute(
                'editor',
                [
                    'fileName' => sprintf('%s.%s', Uuid::v4(), 'odt'),
                ]
            );
    }
}
