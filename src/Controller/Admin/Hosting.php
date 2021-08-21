<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class Hosting extends DashboardController
{
    private ResponderInterface $responder;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        WopiDiscoveryInterface $wopiDiscovery,
        ResponderInterface $responder
    ) {
        $this->wopiDiscovery = $wopiDiscovery;
        $this->responder = $responder;
    }

    /**
     * @Route(path="hosting_capabilities", name="hosting_capabilities")
     */
    public function capabilities(): Response
    {
        try {
            $capabilities = $this->wopiDiscovery->getCapabilities();
        } catch (Throwable $e) {
            $capabilities = [
                'error' => $e->getMessage(),
            ];
        }

        return $this
            ->responder
            ->render(
                '@WopiTest/hosting.html.twig',
                [
                    'capabilities' => $capabilities,
                ]
            );
    }
}
