<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;

final class Hosting
{
    private ResponderInterface $responder;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(ResponderInterface $responder, WopiDiscoveryInterface $wopiDiscovery)
    {
        $this->responder = $responder;
        $this->wopiDiscovery = $wopiDiscovery;
    }

    public function capabilities()
    {
        return $this
            ->responder
            ->json($this->wopiDiscovery->getCapabilities());
    }
}
