<?php

namespace Cleup\Cache\Drivers;

use Cleup\Cache\Exceptions\CacheException;

class MemcachedDriver extends AbstractDriver
{
    /**
     * @var \Memcached $memcached
     */
    private $memcached;

    /**
     * @var bool $isConnected
     */
    private bool $isConnected = false;

    /**
     * MemcachedDriver constructor
     * 
     * @param array $config Configuration options
     * @throws CacheException
     */
    public function __construct(array $config = [])
    {
        if (!extension_loaded('memcached')) {
            throw new CacheException("Memcached extension is not installed");
        }

        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 11211,
            'options' => [],
            'prefix' => 'cache:',
            'default_ttl' => 3600
        ], $config);

        $this->connect();
    }

    /**
     * Set memcached host
     * 
     * @param string $host Server host
     * @return self
     */
    public function host(string $host): self
    {
        $this->config['host'] = $host;
        return $this;
    }

    /**
     * Set memcached port
     * 
     * @param int $port Server port
     * @return self
     */
    public function port(int $port): self
    {
        $this->config['port'] = $port;
        return $this;
    }

    /**
     * Set memcached option
     * 
     * @param int $option Option constant
     * @param mixed $value Option value
     * @return self
     */
    public function setOption(int $option, mixed $value): self
    {
        $this->config['options'][$option] = $value;
        if ($this->isConnected) {
            $this->memcached->setOption($option, $value);
        }
        return $this;
    }

    /**
     * Set key prefix
     * 
     * @param string $prefix Key prefix
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->config['prefix'] = $prefix;
        if ($this->isConnected) {
            $className = '\Memcached';
            $this->memcached->setOption($className::OPT_PREFIX_KEY, $prefix);
        }
        return $this;
    }

    /**
     * Get item from cache
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null if not found
     */
    public function get(string $key): mixed
    {
        $this->validateKey($key);

        if (!$this->isConnected) {
            return null;
        }

        try {
            $className = '\Memcached';
            $value = $this->memcached->get($key);
            return $this->memcached->getResultCode() === $className::RES_NOTFOUND ? null : $value;
        } catch (\Exception $e) {
            throw new CacheException("Memcached get error: " . $e->getMessage());
        }
    }

    /**
     * Store item in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);
        $this->validateTtl($ttl);

        if (!$this->isConnected) {
            return false;
        }

        try {
            $actualTtl = $ttl ?? $this->config['default_ttl'];
            return $this->memcached->set($key, $value, $actualTtl);
        } catch (\Exception $e) {
            throw new CacheException("Memcached set error: " . $e->getMessage());
        }
    }

    /**
     * Delete item from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->memcached->delete($key);
        } catch (\Exception $e) {
            throw new CacheException("Memcached delete error: " . $e->getMessage());
        }
    }

    /**
     * Clear all cache items
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->memcached->flush();
        } catch (\Exception $e) {
            throw new CacheException("Memcached clear error: " . $e->getMessage());
        }
    }

    /**
     * Check if item exists in cache
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);

        if (!$this->isConnected) {
            return false;
        }

        $className = '\Memcached';
        $this->memcached->get($key);
        return $this->memcached->getResultCode() === $className::RES_SUCCESS;
    }

    /**
     * Get multiple items from cache
     * 
     * @param array $keys Array of cache keys
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys): array
    {
        if (!$this->isConnected) {
            return array_fill_keys($keys, null);
        }

        try {
            $values = $this->memcached->getMulti($keys);

            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $values[$key] ?? null;
            }

            return $result;
        } catch (\Exception $e) {
            throw new CacheException("Memcached getMulti error: " . $e->getMessage());
        }
    }

    /**
     * Store multiple items in cache
     * 
     * @param array $values Key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $this->validateTtl($ttl);

        if (!$this->isConnected) {
            return false;
        }

        try {
            $actualTtl = $ttl ?? $this->config['default_ttl'];
            return $this->memcached->setMulti($values, $actualTtl);
        } catch (\Exception $e) {
            throw new CacheException("Memcached setMulti error: " . $e->getMessage());
        }
    }

    /**
     * Delete multiple items from cache
     * 
     * @param array $keys Array of cache keys
     * @return bool Success status
     */
    public function deleteMultiple(array $keys): bool
    {
        if (!$this->isConnected) {
            return false;
        }

        try {
            $results = $this->memcached->deleteMulti($keys);
            return !in_array(false, $results, true);
        } catch (\Exception $e) {
            throw new CacheException("Memcached deleteMulti error: " . $e->getMessage());
        }
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
        $this->validateKey($key);

        if (!$this->isConnected) {
            return false;
        }

        try {
            $className = '\Memcached';
            $result = $this->memcached->increment($key, $value);
            if ($result === false && $this->memcached->getResultCode() === $className::RES_NOTFOUND) {
                $this->set($key, $value);
                return $value;
            }
            return $result;
        } catch (\Exception $e) {
            throw new CacheException("Memcached increment error: " . $e->getMessage());
        }
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
        $this->validateKey($key);
        $className = '\Memcached';

        if (!$this->isConnected) {
            return false;
        }

        try {
            $result = $this->memcached->decrement($key, $value);

            if ($result === false && $this->memcached->getResultCode() === $className::RES_NOTFOUND) {
                $this->set($key, -$value);
                return -$value;
            }
            return $result;
        } catch (\Exception $e) {
            throw new CacheException("Memcached decrement error: " . $e->getMessage());
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics data
     */
    public function getStats(): array
    {
        if (!$this->isConnected) {
            return ['driver' => 'memcached', 'connected' => false];
        }

        try {
            $stats = $this->memcached->getStats();
            $serverStats = reset($stats) ?: [];

            return [
                'driver' => 'memcached',
                'connected' => $this->isConnected,
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'pid' => $serverStats['pid'] ?? 0,
                'uptime' => $serverStats['uptime'] ?? 0,
                'bytes' => $serverStats['bytes'] ?? 0,
                'get_hits' => $serverStats['get_hits'] ?? 0,
                'get_misses' => $serverStats['get_misses'] ?? 0
            ];
        } catch (\Exception $e) {
            return ['driver' => 'memcached', 'connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if driver is connected
     * 
     * @return bool Connection status
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Connect to memcached server
     * 
     * @return self
     * @throws CacheException
     */
    public function connect(): self
    {
        try {
            $className = '\Memcached';
            $this->memcached = new $className();

            // Set options
            foreach ($this->config['options'] as $option => $value) {
                $this->memcached->setOption($option, $value);
            }

            // Set prefix
            $this->memcached->setOption($className::OPT_PREFIX_KEY, $this->config['prefix']);

            // Add server
            $this->isConnected = $this->memcached->addServer(
                $this->config['host'],
                $this->config['port']
            );

            // Test connection
            if (!$this->isConnected || $this->memcached->set('connection_test', 'test', 1) === false) {
                throw new CacheException("Cannot connect to Memcached server");
            }

            return $this;
        } catch (\Exception $e) {
            $this->isConnected = false;
            throw new CacheException("Memcached connection failed: " . $e->getMessage());
        }
    }
}
