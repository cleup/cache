<?php

namespace Cleup\Cache;

use Cleup\Cache\Cache;
use Cleup\Cache\Drivers\LocalDriver;
use Cleup\Cache\Drivers\RedisDriver;
use Cleup\Cache\Drivers\MemcachedDriver;
use Cleup\Cache\Exceptions\CacheException;
use Cleup\Cache\Interfaces\CacheInterface;

class CacheManager
{
    /**
     * @var array $drivers
     */
    private static array $drivers = [];

    /**
     * @var array $unnamedInstances
     */
    private static array $unnamedInstances  = [];

    /**
     * @var string $defaultDriver
     */
    private static string $defaultDriver = 'local';

    /**
     * @var string|null $currentDriver
     */
    private ?string $currentDriver = null;

    /**
     * Configure cache drivers
     * 
     * @param array $config Configuration array
     * @throws CacheException
     */
    public static function configure(array $config): void
    {
        foreach ($config as $name => $driverConfig) {
            if ($name === 'default') {
                if (is_string($driverConfig)) {
                    static::$defaultDriver = $driverConfig;
                }
                continue;
            }

            $driver = static::createDriver($name, $driverConfig);

            // Set default TTL if specified
            if (isset($driverConfig['default_ttl'])) {
                $driver->defaultTtl($driverConfig['default_ttl']);
            }

            static::$drivers[$name] = new Cache($driver, '', false);
        }

        // Set default driver if specified in config
        if (isset($config['default']) && is_string($config['default'])) {
            static::$defaultDriver = $config['default'];
        }
    }

    /**
     * Get all drivers
     * 
     * @return array Driver list
     */
    public static function getDrivers(): array
    {
        return static::$drivers;
    }

    /**
     * Add instances without a name
     * 
     * @return array List of instances
     */
    public static function getUnnamedInstances(): array
    {
        return static::$unnamedInstances;
    }

    /**
     * Add an unnamed driver
     * 
     * @param
     */
    public static function addUnnamedInstance(CacheInterface $instance): void
    {
        static::$unnamedInstances[] = $instance;
    }

    /**
     * Get driver by name
     * 
     * @param string|null $name Driver name
     * @return CacheInterface Cache instance
     * @throws CacheException
     */
    public static function driver(string $name = null): CacheInterface
    {
        $name = $name ?: static::$defaultDriver;

        if (!isset(static::$drivers[$name])) {
            static::$drivers[$name] = static::createDefaultDriver($name);
        }

        return static::$drivers[$name];
    }

    /**
     * Create driver instance
     * 
     * @param string $name Driver name
     * @param array $config Driver configuration
     * @return mixed Driver instance
     * @throws CacheException
     */
    private static function createDriver(string $name, array $config): mixed
    {
        if (str_starts_with($name, 'local')) {
            $driver = new LocalDriver($config);
            if (isset($config['storage_path'])) {
                $driver->storagePath($config['storage_path']);
            }
            return $driver;
        } elseif (str_starts_with($name, 'redis')) {
            if (!extension_loaded('redis')) {
                throw new CacheException("Redis extension is not installed");
            }
            $driver = new RedisDriver($config);
            return $driver->connect();
        } elseif (str_starts_with($name, 'memcached')) {
            if (!extension_loaded('memcached')) {
                throw new CacheException("Memcached extension is not installed");
            }
            $driver = new MemcachedDriver($config);
            return $driver->connect();
        } else {
            throw new CacheException("Unknown driver type: {$name}");
        }
    }

    /**
     * Create default driver instance
     * 
     * @param string $name Driver name
     * @return CacheInterface Cache instance
     * @throws CacheException
     */
    private static function createDefaultDriver(string $name): CacheInterface
    {
        $driver = static::createDriver($name, []);

        return new Cache($driver);
    }

    /**
     * Get the default driver name
     * 
     * @return string Driver name
     */
    public static function getDefaultDriverName(): string
    {
        return static::$defaultDriver;
    }

    /**
     * Parse key for driver determination
     * 
     * @param string $key Cache key
     * @return array [driverName, realKey]
     */
    private static function parseKey(string $key): array
    {
        if (str_contains($key, ':')) {
            $parts = explode(':', $key, 2);
            $driverName = $parts[0];
            $realKey = $parts[1];

            // Check if driver exists or is supported
            if (isset(static::$drivers[$driverName]) || in_array($driverName, ['local', 'redis', 'memcached'])) {
                return [$driverName, $realKey];
            }
        }

        return [static::$defaultDriver, $key];
    }

    /**
     * Set current driver for instance
     * 
     * @param string $name Driver name
     * @return self
     */
    public function setDriver(string $name): self
    {
        $this->currentDriver = $name;
        return $this;
    }

    /**
     * Get current driver name for instance
     * 
     * @return string
     */
    public function getDriverName(): string
    {
        return $this->currentDriver ?: static::$defaultDriver;
    }

    /**
     * Magic method for static calls with namespace support
     * 
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed Method result
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $keyMethods = ['get', 'set', 'has', 'delete', 'pull', 'add', 'forever', 'increment', 'decrement'];

        if (
            in_array($method, $keyMethods) &&
            isset($arguments[0]) &&
            is_string($arguments[0])
        ) {
            [$driverName, $realKey] = static::parseKey($arguments[0]);
            $arguments[0] = $realKey;

            return static::driver($driverName)->$method(...$arguments);
        }

        return static::driver()->$method(...$arguments);
    }

    /**
     * Magic method for instance calls
     * 
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed Method result
     */
    public function __call(string $method, array $arguments)
    {
        $keyMethods = ['get', 'set', 'has', 'delete', 'pull', 'add', 'forever', 'increment', 'decrement'];

        if (
            in_array($method, $keyMethods) &&
            isset($arguments[0]) &&
            is_string($arguments[0])
        ) {
            [$driverName, $realKey] = static::parseKey($arguments[0]);
            $arguments[0] = $realKey;

            // Use instance driver if set, otherwise use parsed driver name
            $driverToUse = $this->currentDriver ?: $driverName;
            return static::driver($driverToUse)->$method(...$arguments);
        }

        // Use instance driver if set, otherwise use default
        $driverToUse = $this->currentDriver ?: static::$defaultDriver;
        return static::driver($driverToUse)->$method(...$arguments);
    }
}
