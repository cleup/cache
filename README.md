# Cleup Cache Library
A powerful, flexible caching library for PHP with support for multiple storage drivers including file-based, Redis, and Memcached.

## Features

- Multiple Drivers: File, Redis, and Memcached support
- Simple API: Intuitive and consistent interface across all drivers
- Namespace Support: Organize cache entries with namespaces
- Memory Caching: Local driver includes in-memory caching for performance
- Garbage Collection: Automatic cleanup of expired entries
- Statistics: Get detailed cache statistics
- Flexible Configuration: Easy setup and configuration

## Installation

```bash
composer require cleup/cache
```

## Basic Setup

### Quick Start

```php
use Cleup\Cache\CacheManager;

// Configure cache drivers
CacheManager::configure([
    'default' => 'local', // or 'type:namespace'
    'local' => [
        'storage_path' => '/path/to/cache',
        'default_ttl' => 3600
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'prefix' => 'app:'
    ]
    'local:myapp' => [
        // ...
    ],
    'redis:new_server': [
        // ...
    ],
    'memcached:sessions' => [
        // ...
    ]
]);

// Use the default driver
CacheManager::set('key', 'value', 3600);
$value = CacheManager::get('key');

// Use a driver with a specific type and namespace.
CacheManager::driver('redis:new_server')->set('key', 'value');
CacheManager::driver('memcached:sessions')->get('key');
```
### Manual Driver Setup

```php
use Cleup\Cache\Drivers\LocalDriver;
use Cleup\Cache\Cache;

// Local file driver
$localDriver = new LocalDriver([
    'storage_path' => '/tmp/cache',
    'default_ttl' => 3600
]);
// Or 
$localDriver = (new LocalDriver())
  ->storagePath('/tmp/cache')
  ->defaultTtl(3600);

$cache = new Cache($localDriver);
$user = ['id' => 1, 'name' => 'Eduard'];
$cache->set('user', $user, 1800);
$cahe->get('user');
```

## Method Reference

### Basic Operations

Retrieve an item from cache.
`get(string $key): mixed`
```php
$value = $cache->get('user_profile');
if ($value === null) {
    // Cache missing
}
```

Store an item in cache.
`set(string $key, mixed $value, ?int $ttl = null): bool`
```php
$success = $cache->set('user', $userData, 3600); // 1 hour
```

Remove an item from cache.
`delete(string $key): bool`
```php
$cache->delete('user');
```

Check if an item exists in cache.
`has(string $key): bool`
```php
if ($cache->has('user:1')) {
    // Item exists
}
```

Clear all cache items.
`clear(): bool`
```php
$cache->clear();
```

### Advanced Operations

Get an item or store the default value if it doesn't exist.
`remember(string $key, callable $callback, ?int $ttl = null): mixed`
```php
$user = $cache->remember('user:1', function() {
    return User::find(1);
}, 3600);
```

Get and remove an item from cache.
`pull(string $key): mixed`
```php
$value = $cache->pull('temporary_data');
```

Store an item only if it doesn't already exist.
`add(string $key, mixed $value, ?int $ttl = null): bool`
```php
$added = $cache->add('unique_key', $value); // Returns false if key exists
```

Store an item permanently (no expiration).
`forever(string $key, mixed $value): bool`
```php
$cache->forever('config_data', $config);
```

### Batch Operations

Get multiple items.
`getMultiple(array $keys): array`
```php
$items = $cache->getMultiple(['user:1', 'user:2', 'user:3']);
```

Store multiple items.
`setMultiple(array $values, ?int $ttl = null): bool`
```php
$success = $cache->setMultiple([
    'user:1' => $user1,
    'user:2' => $user2
], 3600);
```

Delete multiple items.
`deleteMultiple(array $keys): bool`
```php
$cache->deleteMultiple(['user:1', 'user:2']);
```

### Numeric Operations

Increment a numeric value.
`increment(string $key, int $value = 1): int|false`
```php
$newValue = $cache->increment('page_views', 1);
```

Decrement a numeric value.
`decrement(string $key, int $value = 1): int|false`
```php
$newValue = $cache->decrement('remaining_credits', 1);
```

### Utility Methods

Get cache statistics (unique for each driver).
`getStats(): array`
```php
$stats = $cache->getStats();
```

Check if the driver is connected.
`isConnected(): bool`
```php
if ($cache->isConnected()) {
    // Driver is ready
}
```

## Drivers
### Local Driver (File-based)
The local driver stores cache items as files on the filesystem with in-memory caching for performance.
##### Configuration
```php
$driver = new LocalDriver([
    'storage_path' => '/path/to/cache',    // Cache directory
    'file_extension' => '.cache',          // File extension
    'serializer' => 'php',                 // Serialization method
    'gc_probability' => 1,                 // Garbage collection probability
    'gc_divisor' => 100,                   // Garbage collection divisor
    'default_ttl' => 3600                  // Default TTL in seconds
]);
```

##### Fluent Configuration
```php
$driver = (new LocalDriver())
    ->storagePath('/custom/cache/path')
    ->fileExtension('.data')
    ->serializer('php')
    ->garbageCollection(5, 100) // 5% probability
    ->defaultTtl(7200);
```

##### Features
- In-memory caching: Recent items stored in memory for fast access
- File-based storage: Persistent storage on filesystem
- Automatic garbage collection: Removes expired items
- Atomic operations: Safe concurrent access

### Redis Driver
Redis driver for high-performance caching with Redis server.
##### Configuration
```php
$driver = new RedisDriver([
    'host' => '127.0.0.1',         // Redis server host
    'port' => 6379,                // Redis server port
    'timeout' => 2.5,              // Connection timeout
    'persistent' => false,         // Persistent connection
    'password' => 'secret',        // Authentication password
    'database' => 0,               // Redis database number
    'prefix' => 'app:cache:',      // Key prefix
    'default_ttl' => 3600          // Default TTL in seconds
]);
```

##### Fluent Configuration
```php
$driver = (new RedisDriver())
    ->host('redis.example.com')
    ->port(6380)
    ->password('secret')
    ->database(1)
    ->prefix('myapp:')
    ->persistent(true)
    ->defaultTtl(7200)
    ->connect(); // Must call connect() manually with fluent configuration
```
##### Features
- Persistent connections: Optional persistent connections for performance
- Key prefixing: Automatic key namespacing
- Pipeline operations: Efficient batch operations
- Serialization: Automatic serialization of complex data

### Memcached Driver

Memcached driver for distributed caching.

##### Configuration
```php
$driver = new MemcachedDriver([
    'host' => '127.0.0.1',         // Memcached server host
    'port' => 11211,               // Memcached server port
    'options' => [],               // Memcached options
    'prefix' => 'app:',            // Key prefix
    'default_ttl' => 3600          // Default TTL in seconds
]);
```

##### Fluent Configuration
```php
$driver = (new MemcachedDriver())
    ->host('memcached.example.com')
    ->port(11211)
    ->prefix('app:')
    ->setOption(\Memcached::OPT_COMPRESSION, true)
    ->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT)
    ->defaultTtl(7200)
    ->connect(); // Must call connect() manually with fluent configuration
```

##### Features
- Multiple servers: Support for multiple Memcached servers
- Compression: Optional data compression
- Consistent hashing: Better key distribution
- Binary protocol: Optional binary protocol support

## Cache Manager

The Cache Manager provides a static interface for managing multiple cache drivers and namespaces.

### Configuration
```php
use Cleup\Cache\CacheManager;

CacheManager::configure([
    'default' => 'local',
    'local' => [
        'storage_path' => '/tmp/local_cache',
        'default_ttl' => 300
    ],
    'local:forever' => [
        'storage_path' => '/tmp/local_cache',
        'default_ttl' => 0
    ]
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'prefix' => 'redisapp:',
        'default_ttl' => 3600
    ],
    
    'redis:sessions' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1,
        'prefix' => 'sessions:',
        'default_ttl' => 1800
    ],
    'memcached' => [
        'host' => '127.0.0.1',   
        'port' => 11211,       
        'options' => [],      
        'prefix' => 'memcached_app_',        
        'default_ttl' => 3600    
    ]
]);

//Or by creating a new instance of the class
$cacheManager = new CacheManager();
$cacheManager->configure([
    //...
]);
```

### Usage

Direct Driver Access
```php
// Access specific driver
$foreverLocalData = CacheManager::driver('local:forever');
$sessionCache = CacheManager::driver('redis:sessions');

$foreverLocalData->set('data', $value);
$sessionCache->set('user_session', $sessionData);
```

Static Method Calls
```php
// Use default driver
CacheManager::set('key', 'value');
$value = CacheManager::get('key');
```

## Cache Helper Function

The library includes a convenient global helper function cache() that provides a simplified interface for common cache operations.
`cache(?string $key = null, mixed $value = null): mixed`
```php
// Get value by key
$user = cache('user');
// Equivalent to:
$user = CacheManager::get('user');

// Set value with key
cache('user', $userData, 3600);
// Equivalent to:
CacheManager::set('user', $userData, 3600);

# Set Value with TTL

// Set value with specific TTL (using method chaining)
cache()->set('user:123', $userData, 3600);
// Or using the manager directly
cache('user:123', $userData, 3600); // Note: This syntax requires the helper to be extended

// Get CacheManager instance for advanced operations
$cache = cache();
$cache->driver('redis')->remember('key', fn() => compute(), 1800);
```


