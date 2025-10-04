<?php

namespace Cleup\Cache\Drivers;

use Cleup\Cache\Exceptions\CacheException;

class RedisDriver extends AbstractDriver
{
    /**
     * @var \Redis $redis
     */
    private  $redis;

    /**
     * @var bool $isConnected
     */
    private bool $isConnected = false;

    /**
     * RedisDriver constructor
     * 
     * @param array $config Configuration options
     * @throws CacheException
     */
    public function __construct(array $config = [])
    {
        if (!extension_loaded('redis')) {
            throw new CacheException("Redis extension is not installed");
        }

        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 2.5,
            'persistent' => false,
            'password' => null,
            'database' => 0,
            'prefix' => 'cache:',
            'default_ttl' => 3600
        ], $config);

        $this->connect();
    }

    /**
     * Set redis host
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
     * Set redis port
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
     * Set connection timeout
     * 
     * @param float $timeout Timeout in seconds
     * @return self
     */
    public function timeout(float $timeout): self
    {
        $this->config['timeout'] = $timeout;
        return $this;
    }

    /**
     * Set persistent connection
     * 
     * @param bool $persistent Persistent connection flag
     * @return self
     */
    public function persistent(bool $persistent): self
    {
        $this->config['persistent'] = $persistent;
        return $this;
    }

    /**
     * Set redis password
     * 
     * @param string $password Authentication password
     * @return self
     */
    public function password(string $password): self
    {
        $this->config['password'] = $password;
        return $this;
    }

    /**
     * Set redis database
     * 
     * @param int $database Database number
     * @return self
     */
    public function database(int $database): self
    {
        $this->config['database'] = $database;
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
            $value = $this->redis->get($this->config['prefix'] . $key);
            return $value === false ? null : unserialize($value);
        } catch (\Exception $e) {
            throw new CacheException("Redis get error: " . $e->getMessage());
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
            $serializedValue = serialize($value);
            $prefixedKey = $this->config['prefix'] . $key;

            $actualTtl = $ttl ?? $this->config['default_ttl'];

            if ($actualTtl > 0) {
                return $this->redis->setex($prefixedKey, $actualTtl, $serializedValue);
            } else {
                return $this->redis->set($prefixedKey, $serializedValue);
            }
        } catch (\Exception $e) {
            throw new CacheException("Redis set error: " . $e->getMessage());
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
            return $this->redis->del($this->config['prefix'] . $key) > 0;
        } catch (\Exception $e) {
            throw new CacheException("Redis delete error: " . $e->getMessage());
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
            $keys = $this->redis->keys($this->config['prefix'] . '*');
            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }
            return true;
        } catch (\Exception $e) {
            throw new CacheException("Redis clear error: " . $e->getMessage());
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

        try {
            return $this->redis->exists($this->config['prefix'] . $key) > 0;
        } catch (\Exception $e) {
            throw new CacheException("Redis exists error: " . $e->getMessage());
        }
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
            $prefixedKeys = array_map(fn($key) => $this->config['prefix'] . $key, $keys);
            $values = $this->redis->mGet($prefixedKeys);

            $result = [];
            foreach ($keys as $index => $key) {
                $result[$key] = ($values[$index] === false) ? null : unserialize($values[$index]);
            }

            return $result;
        } catch (\Exception $e) {
            throw new CacheException("Redis mGet error: " . $e->getMessage());
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
            $pipeline = $this->redis->pipeline();
            $actualTtl = $ttl ?? $this->config['default_ttl'];

            foreach ($values as $key => $value) {
                $prefixedKey = $this->config['prefix'] . $key;
                $serializedValue = serialize($value);

                if ($actualTtl > 0) {
                    $pipeline->setex($prefixedKey, $actualTtl, $serializedValue);
                } else {
                    $pipeline->set($prefixedKey, $serializedValue);
                }
            }

            $results = $pipeline->exec();
            return !in_array(false, $results, true);
        } catch (\Exception $e) {
            throw new CacheException("Redis pipeline error: " . $e->getMessage());
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
            $prefixedKeys = array_map(fn($key) => $this->config['prefix'] . $key, $keys);
            return $this->redis->del($prefixedKeys) > 0;
        } catch (\Exception $e) {
            throw new CacheException("Redis delete multiple error: " . $e->getMessage());
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
            return $this->redis->incrBy($this->config['prefix'] . $key, $value);
        } catch (\Exception $e) {
            throw new CacheException("Redis increment error: " . $e->getMessage());
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

        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->decrBy($this->config['prefix'] . $key, $value);
        } catch (\Exception $e) {
            throw new CacheException("Redis decrement error: " . $e->getMessage());
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
            return ['driver' => 'redis', 'connected' => false];
        }

        try {
            $info = $this->redis->info();
            return [
                'driver' => 'redis',
                'connected' => $this->isConnected,
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0
            ];
        } catch (\Exception $e) {
            return ['driver' => 'redis', 'connected' => false, 'error' => $e->getMessage()];
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
     * Connect to redis server
     * @return self
     * @throws CacheException
     */
    public function connect(): self
    {
        try {
            $className = '\Redis';
            $this->redis = new $className();

            $connectMethod = $this->config['persistent'] ? 'pconnect' : 'connect';
            $this->isConnected = $this->redis->{$connectMethod}(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );

            if (!$this->isConnected) {
                throw new CacheException("Cannot connect to Redis server");
            }

            if ($this->config['password'] && !$this->redis->auth($this->config['password'])) {
                throw new CacheException("Redis authentication failed");
            }

            if ($this->config['database'] !== 0 && !$this->redis->select($this->config['database'])) {
                throw new CacheException("Cannot select Redis database");
            }

            // Set prefix if supported
            if ($this->config['prefix']) {
                $this->redis->setOption($className::OPT_PREFIX, $this->config['prefix']);
            }

            return $this;
        } catch (\Exception $e) {
            $this->isConnected = false;
            throw new CacheException("Redis connection failed: " . $e->getMessage());
        }
    }

    public function __destruct()
    {
        if (isset($this->redis) && $this->isConnected && !$this->config['persistent']) {
            try {
                $this->redis->close();
            } catch (\Exception $e) {
                // Ignore close errors
            }
        }
    }
}
