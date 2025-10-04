<?php

namespace Cleup\Cache\Interfaces;

interface CacheDriverInterface
{
    /**
     * Get value by key
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null if not found
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
     * Delete value by key
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
     * Check if key exists
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function has(string $key): bool;

    /**
     * Get multiple values by keys
     * 
     * @param array $keys Array of cache keys
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys): array;

    /**
     * Store multiple values
     * 
     * @param array $values Key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values
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
     * Get driver statistics
     * 
     * @return array Statistics data
     */
    public function getStats(): array;

    /**
     * Check driver connection
     * 
     * @return bool Connection status
     */
    public function isConnected(): bool;

    /**
     * Get the storage path (for the local driver only)
     * 
     * @return string Full storage path
     */
    public function getStoragePath(): string;
}
