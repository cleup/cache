<?php

use Cleup\Cache\CacheManager;

/**
 * Cache helper function for simplified cache operations
 * 
 * @param string|null $key Cache key. If provided alone, returns the cached value
 * @param mixed $value Value to cache. If provided with key, stores the value in cache
 * @param int|null $ttl Time to live in seconds
 * @return mixed Returns cached value if only key provided, bool success if key+value provided, CacheManager instance if no args
 */
if (!function_exists('cache')) {
    function cache(
        ?string $key = null,
        mixed $value = null,
        ?int $ttl = null
    ) {
        if (!is_null($key)) {
            if (!is_null($value)) {
                return CacheManager::set($key, $value, $ttl);
            } else if (is_string($key)) {
                return CacheManager::get($key);
            }
        }

        return new CacheManager();
    }
}
