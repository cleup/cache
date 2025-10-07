<?php
// tests/Unit/CalculatorTest.php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Cleup\Cache\Cache;
use Cleup\Cache\Drivers\LocalDriver;
use Cleup\Cache\Drivers\MemcachedDriver;
use Cleup\Cache\Drivers\RedisDriver;

class CacheTest extends TestCase
{
    public function testLocalDriver()
    {
        $localDriver = (new LocalDriver())
            ->storagePath(__DIR__ . '/../cache')
            ->defaultTtl(3600);

        $cache = new Cache($localDriver);
        $cache->set('local_key', 'Local');
        $data = $cache->get('local_key');

        $this->assertSame('Local', $data);
    }

    public function testRedisDriver()
    {
        $localDriver = (new RedisDriver())
            ->defaultTtl(7800);

        $cache = new Cache($localDriver);
        $cache->set('redis_key', 'Redis');
        $data = $cache->get('redis_key');

        $this->assertSame('Redis', $data);
    }

    public function testMemcachedDriver()
    {
        $localDriver = (new MemcachedDriver())
            ->defaultTtl(7800);

        $cache = new Cache($localDriver);
        $cache->set('mc_key', 'Memcached');
        $data = $cache->get('mc_key');

        $this->assertSame('Memcached', $data);
    }
}
