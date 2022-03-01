<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Configuration;

use ChampsLibres\WopiLib\Contract\Service\Configuration\ConfigurationInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Security;

use function array_key_exists;

final class ConfigurableWopiConfiguration implements ConfigurationInterface
{
    private CacheItemPoolInterface $cache;

    private ConfigurationInterface $properties;

    private Security $security;

    public function __construct(
        CacheItemPoolInterface $cache,
        ConfigurationInterface $properties,
        Security $security
    ) {
        $this->cache = $cache;
        $this->properties = $properties;
        $this->security = $security;
    }

    public function jsonSerialize(): array
    {
        $configuration = [];

        if (null !== $this->security->getUser()) {
            $cacheItem = $this->cache->getItem((string) $this->security->getUser());
            $configuration = (array) $cacheItem->get();
        }

        return array_merge(
            $this->properties->jsonSerialize(),
            $configuration
        );
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->jsonSerialize());
    }

    public function offsetGet($offset)
    {
        return $this->jsonSerialize()[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $configuration = $this->jsonSerialize();
        $configuration[$offset] = $value;

        if (null !== $this->security->getUser()) {
            $cacheItem = $this->cache->getItem((string) $this->security->getUser());
            $cacheItem->set($configuration);
            $this->cache->save($cacheItem);
        }
    }

    public function offsetUnset($offset): void
    {
        $configuration = $this->jsonSerialize();
        unset($configuration[$offset]);

        if (null !== $this->security->getUser()) {
            $cacheItem = $this->cache->getItem((string) $this->security->getUser());
            $cacheItem->set($configuration);
            $this->cache->save($cacheItem);
        }
    }
}
