<?php

namespace Cleup\Cache\Interfaces;

use Cleup\Cache\Interfaces\CacheDriverInterface;

interface CacheInterface
{
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    public function get(string $key): mixed;

    /**
     * Store value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Get value or store default if not exists
     * 
     * @param string $key Cache key
     * @param callable $callback Default value callback
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;

    /**
     * Get value and delete it
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    public function pull(string $key): mixed;

    /**
     * Store value only if key doesn't exist
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Store value forever (no expiration)
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @return bool Success status
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Delete value from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache
     * 
     * @return bool Success status
     */
    public function clear(): bool;

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function has(string $key): bool;

    /**
     * Get multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys): array;

    /**
     * Store multiple values in cache
     * 
     * @param array $values Key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @return bool Success status
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Increment numeric value
     * 
     * @param string $key Cache key
     * @param int $value Increment amount
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement numeric value
     * 
     * @param string $key Cache key
     * @param int $value Decrement amount
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Get cache statistics
     * 
     * @return array Statistics data
     */
    public function getStats(): array;

    /**
     * Get underlying driver instance
     * 
     * @return CacheDriverInterface Driver instance
     */
    public function getDriver(): CacheDriverInterface;

    /**
     * Set cache namespace
     * 
     * @param string $namespace Namespace
     * @return self
     */
    public function namespace(string $namespace): self;

    /**
     * Magic getter for property access
     * 
     * @param string $key Cache key
     * @return mixed Cached value
     */
    public function __get(string $key): mixed;

    /**
     * Magic setter for property access
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     */
    public function __set(string $key, mixed $value): void;

    /**
     * Magic isset for property access
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function __isset(string $key): bool;

    /**
     * Magic unset for property access
     * 
     * @param string $key Cache key
     */
    public function __unset(string $key): void;

    /**
     * Get the storage path (for the local driver only)
     * 
     * @return string Full storage path
     */
    public function getStoragePath(): string;

    /**
     * Get the driver type
     * 
     * @return string
     */
    public function getType(): string;

    /**
     * If the instance does not have a code name in the cache manager
     * 
     * @return bool
     */
    public function isUnnamed(): bool;
}