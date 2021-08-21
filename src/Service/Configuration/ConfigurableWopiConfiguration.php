<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Configuration;

use ChampsLibres\WopiLib\Configuration\WopiConfigurationInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_key_exists;

final class ConfigurableWopiConfiguration implements WopiConfigurationInterface
{
    private WopiConfigurationInterface $properties;

    private RequestStack $requestStack;

    public function __construct(WopiConfigurationInterface $properties, RequestStack $requestStack)
    {
        $this->properties = $properties;
        $this->requestStack = $requestStack;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            $this->properties->jsonSerialize(),
            $this
                ->requestStack
                ->getSession()
                ->get('configuration', [])
        );
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->jsonSerialize());
    }

    public function offsetGet($offset)
    {
        return $this->jsonSerialize()[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $configuration = $this->jsonSerialize();
        $configuration[$offset] = $value;
        $this->requestStack->getSession()->set('configuration', $configuration);
    }

    public function offsetUnset($offset)
    {
        $configuration = $this->jsonSerialize();
        unset($configuration[$offset]);
        $this->requestStack->getSession()->set('configuration', $configuration);
    }
}
