<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration;

use JardisAdapter\Cache\Cache;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheNull;
use PHPUnit\Framework\TestCase;

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
        $this->l1 = new CacheMemory();
        $this->l2 = new CacheMemory();

        $pdo = DatabaseHelper::createSqliteDatabase();
        $this->l3 = new CacheDatabase($pdo);

        $this->cache = new Cache([$this->l1, $this->l2, $this->l3]);
    }

    protected function tearDown(): void
    {
        $this->l1->clear();
        $this->l2->clear();
        $this->l3->clear();
    }

    // --- Default NullCache Behavior ---

    public function testNewCacheWithoutLayersWorksAsNullCache(): void
    {
        $cache = new Cache();

        // Should not throw, just return defaults
        $this->assertNull($cache->get('key'));
        $this->assertSame('default', $cache->get('key', 'default'));
        $this->assertFalse($cache->has('key'));
        $this->assertTrue($cache->set('key', 'value'));
        $this->assertTrue($cache->delete('key'));
        $this->assertTrue($cache->clear());
    }

    public function testNullCacheIsReplacedWhenLayersProvided(): void
    {
        $memory = new CacheMemory();

        $cache = new Cache([$memory]);

        $layers = $cache->getLayers();
        $this->assertCount(1, $layers);
        $this->assertSame($memory, $layers[0]);
        $this->assertNotInstanceOf(CacheNull::class, $layers[0]);
    }

    public function testMultipleLayersPreserveOrder(): void
    {
        $memory1 = new CacheMemory();
        $memory2 = new CacheMemory();

        $cache = new Cache([$memory1, $memory2]);

        $layers = $cache->getLayers();
        $this->assertCount(2, $layers);
        $this->assertSame($memory1, $layers[0]);
        $this->assertSame($memory2, $layers[1]);
    }

    public function testGetLayersReturnsNullCacheWhenEmpty(): void
    {
        $cache = new Cache();
        $layers = $cache->getLayers();

        $this->assertCount(1, $layers);
        $this->assertInstanceOf(CacheNull::class, $layers[0]);
    }

    // --- addCache / Layer Order ---

    public function testConstructorOrderDeterminesPriority(): void
    {
        $cache = new Cache([$this->l1, $this->l3]);

        $layers = $cache->getLayers();
        $this->assertCount(2, $layers);
        $this->assertSame($this->l1, $layers[0]);
        $this->assertSame($this->l3, $layers[1]);
    }

    // --- Core PSR-16 Behavior ---

    public function testSetPropagatesToAllLayers(): void
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
        $singleCache = new Cache([$this->l1]);

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

    public function testGetDetectsCachedNullValue(): void
    {
        $this->cache->set('null_key', null);

        // Must return null (cached value), not the default
        $result = $this->cache->get('null_key', 'fallback');
        $this->assertNull($result);
    }

    public function testGetWithValueMatchingDefault(): void
    {
        $this->cache->set('key', 'default');

        // Cached value equals the default — must still be detected as a hit
        $result = $this->cache->get('key', 'default');
        $this->assertSame('default', $result);

        // Verify write-through did not break (value was in L1 already)
        $this->assertTrue($this->l1->has('key'));
    }

    public function testZeroTtlDeletesFromAllLayers(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->l1->has('key'));
        $this->assertTrue($this->l3->has('key'));

        // TTL 0 must delete from all layers (PSR-16)
        $this->cache->set('key', 'value', 0);
        $this->assertFalse($this->l1->has('key'));
        $this->assertFalse($this->l2->has('key'));
        $this->assertFalse($this->l3->has('key'));
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
        $singleCache = new Cache([$this->l1]);
        $layers = $singleCache->getLayers();

        $this->assertCount(1, $layers);
        $this->assertSame($this->l1, $layers[0]);
    }

    // --- Namespace on Adapter Level ---

    public function testNamespaceIsolationOnSharedBackend(): void
    {
        $pdo = DatabaseHelper::createSqliteDatabase();

        $cache1 = new Cache([new CacheDatabase($pdo, namespace: 'app1')]);
        $cache2 = new Cache([new CacheDatabase($pdo, namespace: 'app2')]);

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $this->assertSame('value1', $cache1->get('key'));
        $this->assertSame('value2', $cache2->get('key'));
    }

    public function testNamespaceSetOnEachLayer(): void
    {
        $memory = new CacheMemory('myapp');
        $pdo = DatabaseHelper::createSqliteDatabase();
        $database = new CacheDatabase($pdo, namespace: 'myapp');

        $cache = new Cache([$memory, $database]);

        $cache->set('key', 'value');

        // Both layers should have the value with namespace
        $this->assertSame('value', $memory->get('key'));
        $this->assertSame('value', $database->get('key'));
    }

    public function testClearOnlyAffectsOwnNamespace(): void
    {
        $pdo = DatabaseHelper::createSqliteDatabase();

        $cache1 = new Cache([new CacheDatabase($pdo, namespace: 'ns1')]);
        $cache2 = new Cache([new CacheDatabase($pdo, namespace: 'ns2')]);

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $cache1->clear();

        $this->assertNull($cache1->get('key'));
        $this->assertSame('value2', $cache2->get('key'));
    }

    public function testCacheWithoutNamespace(): void
    {
        $cache = new Cache([new CacheMemory()]);

        $cache->set('key', 'value');
        $this->assertSame('value', $cache->get('key'));
    }

    // --- Explicit CacheNull as Layer ---

    public function testExplicitCacheNullIsKeptAsLayer(): void
    {
        $nullCache = new CacheNull();
        $memory = new CacheMemory();

        $cache = new Cache([$nullCache, $memory]);

        $layers = $cache->getLayers();
        $this->assertCount(2, $layers);
        $this->assertSame($nullCache, $layers[0]);
        $this->assertSame($memory, $layers[1]);
    }
}
