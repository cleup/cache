<?php

namespace Cleup\Cache;

use Cleup\Cache\Interfaces\CacheDriverInterface;
use Cleup\Cache\Interfaces\CacheInterface;

class Cache implements CacheInterface
{
    /**
     * @var CacheDriverInterface $driver
     */
    private CacheDriverInterface $driver;

    /**
     * @var string $namespace - Cache namespace
     */
    private string $namespace;

    /**
     * Cache constructor
     * 
     * @param CacheDriverInterface $driver Cache driver instance
     * @param string $namespace Cache namespace
     */
    public function __construct(
        CacheDriverInterface $driver,
        string $namespace = 'app',
        private bool $unnamed = true
    ) {
        $this->driver = $driver;
        $this->namespace = $namespace;
        $this->unnamed = $unnamed;

        if ($unnamed)
            CacheManager::addUnnamedInstance($this);
    }

    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    public function get(string $key): mixed
    {
        return $this->driver->get($this->addNamespace($key));
    }

    /**
     * Store value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->driver->set($this->addNamespace($key), $value, $ttl);
    }

    /**
     * Get value or store default if not exists
     * 
     * @param string $key Cache key
     * @param callable $callback Default value callback
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get value and delete it
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    public function pull(string $key): mixed
    {
        $value = $this->get($key);
        $this->delete($key);
        return $value;
    }

    /**
     * Store value only if key doesn't exist
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * Store value forever (no expiration)
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @return bool Success status
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    /**
     * Delete value from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        return $this->driver->delete($this->addNamespace($key));
    }

    /**
     * Clear all cache
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        return $this->driver->clear();
    }

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function has(string $key): bool
    {
        return $this->driver->has($this->addNamespace($key));
    }

    /**
     * Get multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys): array
    {
        $namespacedKeys = array_map(
            fn($key) => $this->addNamespace($key),
            $keys
        );

        $results = $this->driver->getMultiple($namespacedKeys);

        // Map results back to original keys
        $mappedResults = [];
        foreach ($keys as $key) {
            $namespacedKey = $this->addNamespace($key);
            $mappedResults[$key] = $results[$namespacedKey] ?? null;
        }

        return $mappedResults;
    }

    /**
     * Store multiple values in cache
     * 
     * @param array $values Key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $namespacedValues = [];
        foreach ($values as $key => $value) {
            $namespacedValues[$this->addNamespace($key)] = $value;
        }

        return $this->driver->setMultiple($namespacedValues, $ttl);
    }

    /**
     * Delete multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @return bool Success status
     */
    public function deleteMultiple(array $keys): bool
    {
        $namespacedKeys = array_map(
            fn($key) => $this->addNamespace($key),
            $keys
        );

        return $this->driver->deleteMultiple($namespacedKeys);
    }

    /**
     * Increment numeric value
     * 
     * @param string $key Cache key
     * @param int $value Increment amount
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->driver->increment($this->addNamespace($key), $value);
    }

    /**
     * Decrement numeric value
     * 
     * @param string $key Cache key
     * @param int $value Decrement amount
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->driver->decrement($this->addNamespace($key), $value);
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics data
     */
    public function getStats(): array
    {
        return $this->driver->getStats();
    }

    /**
     * Get underlying driver instance
     * 
     * @return CacheDriverInterface Driver instance
     */
    public function getDriver(): CacheDriverInterface
    {
        return $this->driver;
    }

    /**
     * Set cache namespace
     * 
     * @param string $namespace Namespace
     * @return self
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Add namespace to key
     * 
     * @param string $key Original key
     * @return string Namespaced key
     */
    private function addNamespace(string $key): string
    {
        return $this->namespace . ':' . $key;
    }

    /**
     * Magic getter for property access
     * 
     * @param string $key Cache key
     * @return mixed Cached value
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Magic setter for property access
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic isset for property access
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic unset for property access
     * 
     * @param string $key Cache key
     */
    public function __unset(string $key): void
    {
        $this->delete($key);
    }

    /**
     * Get the storage path (for the local driver only)
     * 
     * @return string Full storage path
     */
    public function getStoragePath(): string
    {
        return $this->driver->getStoragePath();
    }

    /**
     * Get the driver type
     * 
     * @return string
     */
    public function getType(): string
    {
        return $this->driver->getType();
    }

    /**
     * If the instance does not have a code name in the cache manager
     * 
     * @return bool
     */
    public function isUnnamed(): bool
    {
        return $this->unnamed;
    }
}
