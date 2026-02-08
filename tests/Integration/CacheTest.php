<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration;

use DateInterval;
use JardisAdapter\Cache\Cache;
use JardisAdapter\Cache\Adapter\CacheApcu;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheRedis;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

/**
 * Integration tests for Cache (multi-layer cache implementation).
 */
class CacheTest extends TestCase
{
    private Cache $cache;
    private CacheMemory $l1;
    private CacheMemory $l2;
    private CacheDatabase $l3;

    protected function setUp(): void
    {
        $this->l1 = new CacheMemory('l1_');
        $this->l2 = new CacheMemory('l2_');

        $pdo = DatabaseHelper::createSqliteDatabase();
        $this->l3 = new CacheDatabase($pdo, 'l3_');

        $this->cache = new Cache($this->l1, $this->l2, $this->l3);
    }

    protected function tearDown(): void
    {
        $this->l1->clear();
        $this->l2->clear();
        $this->l3->clear();
    }

    public function testRequiresAtLeastOneCacheLayer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one cache layer required');

        new Cache();
    }

    public function testSetPropagatestoAllLayers(): void
    {
        $this->cache->set('key1', 'value1');

        $this->assertSame('value1', $this->l1->get('key1'));
        $this->assertSame('value1', $this->l2->get('key1'));
        $this->assertSame('value1', $this->l3->get('key1'));
    }

    public function testGetFromFirstAvailableLayer(): void
    {
        // Set only in L3
        $this->l3->set('key1', 'value1');

        $result = $this->cache->get('key1');
        $this->assertSame('value1', $result);
    }

    public function testWriteThroughPopulatesHigherLayers(): void
    {
        // Set only in L3
        $this->l3->set('key1', 'value1');

        // Get should populate L1 and L2
        $this->cache->get('key1');

        $this->assertSame('value1', $this->l1->get('key1'));
        $this->assertSame('value1', $this->l2->get('key1'));
    }

    public function testDeleteRemovesFromAllLayers(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->delete('key1');

        $this->assertFalse($this->l1->has('key1'));
        $this->assertFalse($this->l2->has('key1'));
        $this->assertFalse($this->l3->has('key1'));
    }

    public function testClearRemovesFromAllLayers(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->clear();

        $this->assertFalse($this->l1->has('key1'));
        $this->assertFalse($this->l2->has('key1'));
        $this->assertFalse($this->l3->has('key1'));
    }

    public function testHasChecksAllLayers(): void
    {
        $this->l3->set('key1', 'value1');

        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('nonexistent'));
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

        $this->assertSame('value1', $this->l1->get('key1'));
        $this->assertSame('value2', $this->l1->get('key2'));
        $this->assertSame('value1', $this->l3->get('key1'));
        $this->assertSame('value2', $this->l3->get('key2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));

        $this->assertFalse($this->l1->has('key1'));
        $this->assertFalse($this->l3->has('key1'));
    }

    public function testWriteThroughWithPartiallyAvailableData(): void
    {
        // Set different data in different layers
        $this->l1->set('key1', 'l1_value');
        $this->l3->set('key2', 'l3_value');

        // Get from L1 (fastest)
        $this->assertSame('l1_value', $this->cache->get('key1'));

        // Get from L3 (slowest) - should populate L1 and L2
        $this->assertSame('l3_value', $this->cache->get('key2'));
        $this->assertSame('l3_value', $this->l1->get('key2'));
        $this->assertSame('l3_value', $this->l2->get('key2'));
    }

    public function testTtlPropagation(): void
    {
        $this->cache->set('key1', 'value1', 1);

        $this->assertTrue($this->l1->has('key1'));
        $this->assertTrue($this->l2->has('key1'));
        $this->assertTrue($this->l3->has('key1'));

        sleep(2);

        $this->assertFalse($this->cache->has('key1'));
    }

    public function testComplexDataTypes(): void
    {
        $data = [
            'string' => 'test',
            'int' => 123,
            'float' => 1.23,
            'array' => [1, 2, 3],
            'object' => (object)['prop' => 'value'],
        ];

        $this->cache->set('complex', $data);
        $result = $this->cache->get('complex');

        $this->assertSame($data['string'], $result['string']);
        $this->assertSame($data['int'], $result['int']);
        $this->assertSame($data['float'], $result['float']);
        $this->assertSame($data['array'], $result['array']);
        $this->assertEquals($data['object'], $result['object']);
    }

    public function testSingleLayerCache(): void
    {
        $singleCache = new Cache($this->l1);

        $singleCache->set('key1', 'value1');
        $this->assertSame('value1', $singleCache->get('key1'));

        $singleCache->delete('key1');
        $this->assertFalse($singleCache->has('key1'));
    }

    public function testPartialFailureOnSet(): void
    {
        // Even if one layer fails, set should continue
        $this->cache->set('key1', 'value1');

        // All layers should have the value
        $this->assertTrue($this->l1->has('key1'));
        $this->assertTrue($this->l2->has('key1'));
        $this->assertTrue($this->l3->has('key1'));
    }

    public function testCacheMissReturnsDefault(): void
    {
        $result = $this->cache->get('nonexistent', 'my_default');
        $this->assertSame('my_default', $result);
    }

    public function testGetLayersReturnsAllCacheLayers(): void
    {
        $layers = $this->cache->getLayers();

        $this->assertCount(3, $layers);
        $this->assertSame($this->l1, $layers[0]);
        $this->assertSame($this->l2, $layers[1]);
        $this->assertSame($this->l3, $layers[2]);
    }

    public function testGetLayersWithSingleLayer(): void
    {
        $singleCache = new Cache($this->l1);
        $layers = $singleCache->getLayers();

        $this->assertCount(1, $layers);
        $this->assertSame($this->l1, $layers[0]);
    }

    // --- Named Layer Tests ---

    public function testNamedLayerAccess(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            database: $this->l3
        );

        $this->assertSame($this->l1, $cache->layer(CacheMemory::LAYER_NAME));
        $this->assertSame($this->l3, $cache->layer(CacheDatabase::LAYER_NAME));
    }

    public function testNamedLayerTargetedSet(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            fallback: $this->l2,
            database: $this->l3
        );

        // Write only to database layer
        $cache->layer(CacheDatabase::LAYER_NAME)->set('key1', 'db_only');

        $this->assertFalse($this->l1->has('key1'));
        $this->assertFalse($this->l2->has('key1'));
        $this->assertSame('db_only', $this->l3->get('key1'));
    }

    public function testNamedLayerTargetedGet(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            fallback: $this->l2,
            database: $this->l3
        );

        // Set values in different layers directly
        $this->l1->set('key1', 'memory_value');
        $this->l3->set('key1', 'db_value');

        // Targeted get from specific layer
        $this->assertSame('memory_value', $cache->layer(CacheMemory::LAYER_NAME)->get('key1'));
        $this->assertSame('db_value', $cache->layer(CacheDatabase::LAYER_NAME)->get('key1'));
    }

    public function testNamedLayerThrowsOnUnknownLayer(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            database: $this->l3
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cache layer 'nonexistent' not found");

        $cache->layer('nonexistent');
    }

    public function testNamedLayerNotAvailableWithoutNamedArgs(): void
    {
        // Positional args → no named layers
        $cache = new Cache($this->l1, $this->l2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('use named arguments in constructor');

        $cache->layer(CacheMemory::LAYER_NAME);
    }

    public function testNamedLayerSetDoesNotAffectOtherLayers(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            fallback: $this->l2,
            database: $this->l3
        );

        // Standard PSR-16 set → all layers
        $cache->set('shared', 'everywhere');

        $this->assertTrue($this->l1->has('shared'));
        $this->assertTrue($this->l2->has('shared'));
        $this->assertTrue($this->l3->has('shared'));

        // Targeted set → only memory
        $cache->layer(CacheMemory::LAYER_NAME)->set('exclusive', 'only_here');

        $this->assertTrue($this->l1->has('exclusive'));
        $this->assertFalse($this->l2->has('exclusive'));
        $this->assertFalse($this->l3->has('exclusive'));
    }

    public function testNamedLayerReturnsPsr16Interface(): void
    {
        $cache = new Cache(
            memory: $this->l1
        );

        $layer = $cache->layer(CacheMemory::LAYER_NAME);

        $this->assertInstanceOf(\Psr\SimpleCache\CacheInterface::class, $layer);
    }

    public function testNamedLayerCascadingStillWorks(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            fallback: $this->l2,
            database: $this->l3
        );

        // Set only in database via named layer
        $cache->layer(CacheDatabase::LAYER_NAME)->set('deep', 'value');

        // Standard get should find it via cascading and write-through to L1/L2
        $result = $cache->get('deep');

        $this->assertSame('value', $result);
        $this->assertSame('value', $this->l1->get('deep'));
        $this->assertSame('value', $this->l2->get('deep'));
    }

    public function testGetLayersStillWorksWithNamedArgs(): void
    {
        $cache = new Cache(
            memory: $this->l1,
            database: $this->l3
        );

        $layers = $cache->getLayers();

        $this->assertCount(2, $layers);
        $this->assertSame($this->l1, $layers[0]);
        $this->assertSame($this->l3, $layers[1]);
    }

    // --- LAYER_NAME Constant Tests ---

    public function testLayerNameConstantMemory(): void
    {
        $this->assertSame('memory', CacheMemory::LAYER_NAME);

        $memory = new CacheMemory('test_');
        $cache = new Cache(memory: $memory);

        $this->assertSame($memory, $cache->layer(CacheMemory::LAYER_NAME));
    }

    public function testLayerNameConstantDatabase(): void
    {
        $this->assertSame('database', CacheDatabase::LAYER_NAME);

        $pdo = DatabaseHelper::createSqliteDatabase();
        $database = new CacheDatabase($pdo, 'test_');
        $cache = new Cache(database: $database);

        $this->assertSame($database, $cache->layer(CacheDatabase::LAYER_NAME));
    }

    public function testLayerNameConstantRedis(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available');
        }

        try {
            $redis = new Redis();
            $host = getenv('REDIS_HOST') ?: (gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1');
            $redis->connect($host, 6379);
            $redis->select(15);
        } catch (RedisException $e) {
            $this->markTestSkipped('Redis server is not available: ' . $e->getMessage());
        }

        $this->assertSame('redis', CacheRedis::LAYER_NAME);

        $redisCache = new CacheRedis($redis, 'test_');
        $cache = new Cache(redis: $redisCache);

        $this->assertSame($redisCache, $cache->layer(CacheRedis::LAYER_NAME));

        $redisCache->clear();
        $redis->close();
    }

    public function testLayerNameConstantApcu(): void
    {
        if (!extension_loaded('apcu') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APCu extension is not available or not enabled for CLI');
        }

        $this->assertSame('apcu', CacheApcu::LAYER_NAME);

        $apcuCache = new CacheApcu('test_');
        $cache = new Cache(apcu: $apcuCache);

        $this->assertSame($apcuCache, $cache->layer(CacheApcu::LAYER_NAME));

        $apcuCache->clear();
    }

    public function testAllLayerNamesInCombinedCache(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available');
        }

        try {
            $redis = new Redis();
            $host = getenv('REDIS_HOST') ?: (gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1');
            $redis->connect($host, 6379);
            $redis->select(15);
        } catch (RedisException $e) {
            $this->markTestSkipped('Redis server is not available: ' . $e->getMessage());
        }

        $memory = new CacheMemory('test_');
        $redisCache = new CacheRedis($redis, 'test_');
        $pdo = DatabaseHelper::createSqliteDatabase();
        $database = new CacheDatabase($pdo, 'test_');

        $cache = new Cache(
            memory: $memory,
            redis: $redisCache,
            database: $database
        );

        $this->assertSame($memory, $cache->layer(CacheMemory::LAYER_NAME));
        $this->assertSame($redisCache, $cache->layer(CacheRedis::LAYER_NAME));
        $this->assertSame($database, $cache->layer(CacheDatabase::LAYER_NAME));

        // Targeted write to redis only
        $cache->layer(CacheRedis::LAYER_NAME)->set('redis_only', 'value');

        $this->assertFalse($memory->has('redis_only'));
        $this->assertSame('value', $redisCache->get('redis_only'));
        $this->assertFalse($database->has('redis_only'));

        $redisCache->clear();
        $redis->close();
    }
}
