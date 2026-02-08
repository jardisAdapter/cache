<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use DateInterval;
use JardisAdapter\Cache\Adapter\CacheRedis;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

/**
 * Integration tests for CacheRedis implementation.
 */
class CacheRedisTest extends TestCase
{
    private Redis $redis;
    private CacheRedis $cache;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available');
        }

        try {
            $this->redis = new Redis();
            // Use 'redis' hostname when running in Docker, fallback to localhost
            $host = getenv('REDIS_HOST') ?: (gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1');
            $this->redis->connect($host, 6379);
            $this->redis->select(15); // Use database 15 for testing
        } catch (RedisException $e) {
            $this->markTestSkipped('Redis server is not available: ' . $e->getMessage());
        }

        $this->cache = new CacheRedis($this->redis, 'test_');
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
        }

        if (isset($this->redis)) {
            $this->redis->close();
        }
    }

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('key1', 'value1'));
        $this->assertSame('value1', $this->cache->get('key1'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->delete('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testSetWithTtl(): void
    {
        $this->cache->set('key1', 'value1', 1);
        $this->assertTrue($this->cache->has('key1'));
        sleep(2);
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testSetWithDateIntervalTtl(): void
    {
        $this->cache->set('key1', 'value1', new DateInterval('PT1S'));
        $this->assertTrue($this->cache->has('key1'));
        sleep(2);
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertSame('value1', $result['key1']);
        $this->assertSame('value2', $result['key2']);
        $this->assertSame('default', $result['key3']);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->assertTrue($this->cache->setMultiple($values));
        $this->assertSame('value1', $this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testComplexDataTypes(): void
    {
        $data = [
            'string' => 'test',
            'int' => 123,
            'float' => 1.23,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $this->cache->set('complex', $data);
        $result = $this->cache->get('complex');

        $this->assertSame($data['string'], $result['string']);
        $this->assertSame($data['int'], $result['int']);
        $this->assertSame($data['float'], $result['float']);
        $this->assertSame($data['array'], $result['array']);
        $this->assertSame($data['nested'], $result['nested']);
    }

    public function testNamespace(): void
    {
        $cache1 = new CacheRedis($this->redis, 'namespace1_');
        $cache2 = new CacheRedis($this->redis, 'namespace2_');

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $this->assertSame('value1', $cache1->get('key'));
        $this->assertSame('value2', $cache2->get('key'));

        // Cleanup
        $cache1->clear();
        $cache2->clear();
    }

    public function testClearWithNamespace(): void
    {
        $cache1 = new CacheRedis($this->redis, 'ns1_');
        $cache2 = new CacheRedis($this->redis, 'ns2_');

        $cache1->set('key1', 'value1');
        $cache2->set('key1', 'value2');

        $cache1->clear();

        $this->assertFalse($cache1->has('key1'));
        $this->assertTrue($cache2->has('key1')); // Should not be affected

        // Cleanup
        $cache2->clear();
    }

    public function testRedisConnectionFailureHandling(): void
    {
        // Create a Redis instance with a bad connection
        $badRedis = new Redis();

        try {
            $badRedis->connect('invalid-host-that-does-not-exist', 9999, 0.1);
            $this->fail('Expected RedisException to be thrown');
        } catch (RedisException $e) {
            // Connection failure message can vary
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testLargeDataStorage(): void
    {
        // Test with large data (1MB string)
        $largeData = str_repeat('x', 1024 * 1024);

        $this->cache->set('large', $largeData);
        $result = $this->cache->get('large');

        $this->assertEquals($largeData, $result);
    }

    public function testSetWithVeryLongKey(): void
    {
        // Redis keys can be very long, test with 1000 char key
        $longKey = str_repeat('k', 1000);

        $this->cache->set($longKey, 'value');
        $this->assertEquals('value', $this->cache->get($longKey));
    }

    public function testExpiredKeyCleanup(): void
    {
        // Set a key with 1 second TTL
        $this->cache->set('expire_test', 'value', 1);
        $this->assertTrue($this->cache->has('expire_test'));

        // Wait for expiration
        sleep(2);

        // Key should be gone
        $this->assertFalse($this->cache->has('expire_test'));
        $this->assertNull($this->cache->get('expire_test'));
    }

    public function testDeleteNonExistentKey(): void
    {
        // Deleting a non-existent key should return true (no-op)
        $result = $this->cache->delete('does_not_exist');
        $this->assertTrue($result);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('overwrite', 'value1');
        $this->assertEquals('value1', $this->cache->get('overwrite'));

        $this->cache->set('overwrite', 'value2');
        $this->assertEquals('value2', $this->cache->get('overwrite'));
    }

    public function testSpecialCharactersInKeys(): void
    {
        $specialKeys = [
            'key:with:colons',
            'key/with/slashes',
            'key.with.dots',
            'key-with-dashes',
            'key_with_underscores',
        ];

        foreach ($specialKeys as $key) {
            $this->cache->set($key, "value_$key");
            $this->assertEquals("value_$key", $this->cache->get($key));
        }
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'ttl_key1' => 'value1',
            'ttl_key2' => 'value2',
        ];

        $this->assertTrue($this->cache->setMultiple($values, 1));
        $this->assertTrue($this->cache->has('ttl_key1'));
        $this->assertTrue($this->cache->has('ttl_key2'));

        sleep(2);

        $this->assertFalse($this->cache->has('ttl_key1'));
        $this->assertFalse($this->cache->has('ttl_key2'));
    }

    public function testSetWithNullTtl(): void
    {
        // Null TTL should mean no expiration
        $this->cache->set('no_expire', 'value', null);

        sleep(1);

        $this->assertTrue($this->cache->has('no_expire'));
        $this->assertEquals('value', $this->cache->get('no_expire'));
    }

    public function testGetMultipleWithMixedResults(): void
    {
        $this->cache->set('exists1', 'value1');
        $this->cache->set('exists2', 'value2');

        $result = $this->cache->getMultiple(
            ['exists1', 'missing', 'exists2', 'also_missing'],
            'default_value'
        );

        $this->assertEquals('value1', $result['exists1']);
        $this->assertEquals('value2', $result['exists2']);
        $this->assertEquals('default_value', $result['missing']);
        $this->assertEquals('default_value', $result['also_missing']);
    }

    public function testDeleteMultipleWithEmptyArray(): void
    {
        $result = $this->cache->deleteMultiple([]);
        $this->assertTrue($result);
    }

    public function testSetMultipleWithEmptyArray(): void
    {
        $result = $this->cache->setMultiple([]);
        $this->assertTrue($result);
    }

    public function testGetMultipleWithEmptyArray(): void
    {
        $result = $this->cache->getMultiple([]);
        $this->assertIsIterable($result);
        $this->assertEmpty($result);
    }

    public function testBooleanValues(): void
    {
        $this->cache->set('true_val', true);
        $this->cache->set('false_val', false);

        $this->assertTrue($this->cache->get('true_val'));
        $this->assertFalse($this->cache->get('false_val'));
    }

    public function testNullValue(): void
    {
        $this->cache->set('null_val', null);
        $this->assertNull($this->cache->get('null_val'));
    }

    public function testIntegerValue(): void
    {
        $this->cache->set('int_val', 42);
        $this->assertEquals(42, $this->cache->get('int_val'));
    }

    public function testFloatValue(): void
    {
        $this->cache->set('float_val', 3.14159);
        $this->assertEquals(3.14159, $this->cache->get('float_val'));
    }

    public function testEmptyStringValue(): void
    {
        $this->cache->set('empty_string', '');
        $this->assertEquals('', $this->cache->get('empty_string'));
    }

    public function testClearWithoutNamespace(): void
    {
        // Create cache without namespace (dangerous clear)
        $redis = new Redis();
        $host = getenv('REDIS_HOST') ?: (gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1');
        $redis->connect($host, 6379);
        $redis->select(14); // Use different DB for this test

        $cache = new CacheRedis($redis); // No namespace

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        // Clear should flush entire DB
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));

        $redis->close();
    }

    public function testSetWithZeroTtl(): void
    {
        // Zero TTL should not set expiration
        $this->cache->set('zero_ttl', 'value', 0);

        sleep(1);

        $this->assertTrue($this->cache->has('zero_ttl'));
    }

    public function testSetWithNegativeTtl(): void
    {
        // Negative TTL should not set expiration
        $this->cache->set('neg_ttl', 'value', -10);

        sleep(1);

        $this->assertTrue($this->cache->has('neg_ttl'));
    }

    public function testSetWithDateIntervalZero(): void
    {
        // DateInterval with 0 duration
        $interval = new DateInterval('PT0S');
        $this->cache->set('interval_zero', 'value', $interval);

        $this->assertTrue($this->cache->has('interval_zero'));
    }

    public function testGetConnectionReturnsRedisInstance(): void
    {
        $connection = $this->cache->getConnection();

        $this->assertInstanceOf(Redis::class, $connection);
    }

    public function testGetConnectionReturnsSameInstance(): void
    {
        $connection1 = $this->cache->getConnection();
        $connection2 = $this->cache->getConnection();

        $this->assertSame($connection1, $connection2);
    }

    public function testGetConnectionReturnsInjectedInstance(): void
    {
        $connection = $this->cache->getConnection();

        $this->assertSame($this->redis, $connection);
    }

    public function testGetConnectionAllowsDirectRedisOperations(): void
    {
        $connection = $this->cache->getConnection();

        // Direct Redis operation outside PSR-16
        $connection->set('direct_key', 'direct_value');
        $result = $connection->get('direct_key');

        $this->assertEquals('direct_value', $result);

        // Cleanup
        $connection->del('direct_key');
    }
}
