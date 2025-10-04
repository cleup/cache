<?php

namespace Cleup\Cache\Drivers;

use Cleup\Cache\Exceptions\CacheException;

class LocalDriver extends AbstractDriver
{
    /**
     * @var array $memoryCache
     */
    private array $memoryCache = [];

    /**
     * LocalDriver constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'storage_path' => sys_get_temp_dir() . '/cleup_cache',
            'file_extension' => '.cache',
            'serializer' => 'php',
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'default_ttl' => 3600
        ], $config);

        $this->ensureDirectoryExists();
    }

    /**
     * Set storage path for cache files
     * 
     * @param string $path Directory path
     * @return self
     */
    public function storagePath(string $path): self
    {
        $this->config['storage_path'] = $path;
        $this->ensureDirectoryExists();
        return $this;
    }

    /**
     * Set file extension for cache files
     * 
     * @param string $extension File extension
     * @return self
     */
    public function fileExtension(string $extension): self
    {
        $this->config['file_extension'] = $extension;
        return $this;
    }

    /**
     * Set serializer type
     * 
     * @param string $serializer Serializer name
     * @return self
     */
    public function serializer(string $serializer): self
    {
        $this->config['serializer'] = $serializer;
        return $this;
    }

    /**
     * Configure garbage collection probability
     * 
     * @param int $probability GC probability
     * @param int $divisor GC divisor
     * @return self
     */
    public function garbageCollection(int $probability, int $divisor): self
    {
        $this->config['gc_probability'] = $probability;
        $this->config['gc_divisor'] = $divisor;
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

        // Check memory cache first
        if (isset($this->memoryCache[$key]) && $this->memoryCache[$key]['expires'] > time()) {
            return $this->memoryCache[$key]['value'];
        }

        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return null;
        }

        try {
            $content = file_get_contents($filename);
            if ($content === false) {
                return null;
            }

            $data = unserialize($content);
            if (!is_array($data) || !isset($data['expires'])) {
                unlink($filename);
                return null;
            }

            if ($data['expires'] > 0 && $data['expires'] < time()) {
                $this->delete($key);
                return null;
            }

            // Store in memory for fast access
            $this->memoryCache[$key] = $data;

            return $data['value'];
        } catch (\Exception $e) {
            throw new CacheException("Failed to read cache file: " . $e->getMessage());
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

        $filename = $this->getFilename($key);
        $expires = $ttl ? (time() + $ttl) : ($this->config['default_ttl'] ? time() + $this->config['default_ttl'] : 0);

        $data = [
            'value' => $value,
            'expires' => $expires,
            'created' => time(),
            'key' => $key
        ];

        // Store in memory
        $this->memoryCache[$key] = $data;

        // Store on disk
        $tempFile = tempnam($this->config['storage_path'], 'tmp_');

        try {
            if (file_put_contents($tempFile, serialize($data), LOCK_EX) === false) {
                return false;
            }

            return rename($tempFile, $filename);
        } catch (\Exception $e) {
            @unlink($tempFile);
            throw new CacheException("Failed to write cache file: " . $e->getMessage());
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

        unset($this->memoryCache[$key]);
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    /**
     * Clear all cache items
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        $this->memoryCache = [];
        $pattern = $this->config['storage_path'] . '/*' . $this->config['file_extension'];
        $files = glob($pattern);

        $success = true;
        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if item exists in cache
     * 
     * @param string $key Cache key
     * @return bool Existence status
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get multiple items from cache
     * 
     * @param array $keys Array of cache keys
     * @return array Key-value pairs
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
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

        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete multiple items from cache
     * 
     * @param array $keys Array of cache keys
     * @return bool Success status
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
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

        $current = $this->get($key) ?? 0;
        if (!is_numeric($current)) {
            return false;
        }

        $newValue = (int)$current + $value;
        return $this->set($key, $newValue) ? $newValue : false;
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
        return $this->increment($key, -$value);
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics data
     */
    public function getStats(): array
    {
        $pattern = $this->config['storage_path'] . '/*' . $this->config['file_extension'];
        $files = glob($pattern) ?: [];
        $totalSize = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }

        return [
            'driver' => 'local',
            'files_count' => count($files),
            'total_size' => $totalSize,
            'memory_items' => count($this->memoryCache),
            'storage_path' => $this->config['storage_path']
        ];
    }

    /**
     * Check if driver is connected
     * 
     * @return bool Connection status
     */
    public function isConnected(): bool
    {
        return is_writable($this->config['storage_path']) && is_readable($this->config['storage_path']);
    }

    /**
     * Get the storage path
     * 
     * @return string Full storage path
     */
    public function getStoragePath(): string
    {
        return $this->config['storage_path'];
    }

    /**
     * Generate filename for cache key
     * 
     * @param string $key Cache key
     * @return string Full file path
     */
    private function getFilename(string $key): string
    {
        $hash = md5($key);
        return $this->config['storage_path'] . '/' . $hash . $this->config['file_extension'];
    }

    /**
     * Ensure cache directory exists
     * 
     * @throws CacheException
     */
    private function ensureDirectoryExists(): void
    {
        $path = $this->config['storage_path'];
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new CacheException("Cannot create cache directory: {$path}");
            }
        }
    }

    /**
     * Run garbage collection
     */
    private function runGarbageCollection(): void
    {
        if (
            $this->config['gc_divisor'] > 0 &&
            random_int(1, $this->config['gc_divisor']) <= $this->config['gc_probability']
        ) {
            $pattern = $this->config['storage_path'] . '/*' . $this->config['file_extension'];
            $files = glob($pattern) ?: [];

            foreach ($files as $file) {
                try {
                    if (!is_file($file)) {
                        continue;
                    }

                    $content = file_get_contents($file);
                    if ($content === false) {
                        continue;
                    }

                    $data = unserialize($content);
                    if (
                        is_array($data) && isset($data['expires']) &&
                        $data['expires'] > 0 && $data['expires'] < time()
                    ) {
                        unlink($file);
                    }
                } catch (\Exception $e) {
                    // Ignore corrupted files
                }
            }
        }
    }

    public function __destruct()
    {
        $this->runGarbageCollection();
    }
}
