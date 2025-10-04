<?php

namespace Cleup\Cache\Drivers;

use Cleup\Cache\Exceptions\InvalidArgumentException;
use Cleup\Cache\Interfaces\CacheDriverInterface;

abstract class AbstractDriver implements CacheDriverInterface
{
    /**
     * @var array $config
     */
    protected array $config = [];

    /**
     * Validate cache key
     * 
     * @param string $key Cache key to validate
     * @throws InvalidArgumentException
     */
    protected function validateKey(string $key): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException("Cache key cannot be empty");
        }

        if (preg_match('/[{}()\/\\@]/', $key)) {
            throw new InvalidArgumentException("Invalid characters in cache key: {$key}");
        }
    }

    /**
     * Validate TTL value
     * 
     * @param int|null $ttl Time to live in seconds
     * @throws InvalidArgumentException
     */
    protected function validateTtl(?int $ttl): void
    {
        if ($ttl !== null && $ttl < 0) {
            throw new InvalidArgumentException("TTL cannot be negative");
        }
    }

    /**
     * Get driver configuration
     * 
     * @return array Current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set default TTL for cache items
     * 
     * @param int $ttl Default time to live in seconds
     * @return self
     */
    public function defaultTtl(int $ttl): self
    {
        $this->config['default_ttl'] = $ttl;
        return $this;
    }
}
