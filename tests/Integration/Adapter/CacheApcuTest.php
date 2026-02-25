<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use DateInterval;
use JardisAdapter\Cache\Adapter\CacheApcu;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CacheApcu implementation.
 */
class CacheApcuTest extends TestCase
{
    private CacheApcu $cache;

    protected function setUp(): void
    {
        if (!extension_loaded('apcu') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APCu extension is not available or not enabled for CLI');
        }

        $this->cache = new CacheApcu('test_');
        $this->cache->clear(); // Clean before each test
    }

    protected function tearDown(): void
    {
        if (isset($this->cache)) {
            $this->cache->clear();
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

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'ttl1' => 'value1',
            'ttl2' => 'value2',
        ];

        $this->assertTrue($this->cache->setMultiple($values, 1));
        $this->assertTrue($this->cache->has('ttl1'));
        $this->assertTrue($this->cache->has('ttl2'));

        sleep(2);

        $this->assertFalse($this->cache->has('ttl1'));
        $this->assertFalse($this->cache->has('ttl2'));
    }

    public function testSetWithNullTtl(): void
    {
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
            ['exists1', 'missing', 'exists2'],
            'default'
        );

        $this->assertEquals('value1', $result['exists1']);
        $this->assertEquals('value2', $result['exists2']);
        $this->assertEquals('default', $result['missing']);
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

    public function testNamespaceIsolation(): void
    {
        $cache1 = new CacheApcu('ns1_');
        $cache2 = new CacheApcu('ns2_');

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $this->assertEquals('value1', $cache1->get('key'));
        $this->assertEquals('value2', $cache2->get('key'));

        // Fixed: clear() now respects namespaces
        $cache1->clear();
        $this->assertNull($cache1->get('key'));
        $this->assertEquals('value2', $cache2->get('key'));

        $cache2->clear();
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
        // Fixed: Now uses array_key_exists() instead of isset()
        $result = $this->cache->get('null_val', 'default');
        $this->assertNull($result);
    }

    public function testIntegerValue(): void
    {
        $this->cache->set('int_val', 42);
        $this->assertEquals(42, $this->cache->get('int_val'));
    }

    public function testFloatValue(): void
    {
        $this->cache->set('float_val', 3.14);
        $this->assertEquals(3.14, $this->cache->get('float_val'));
    }

    public function testEmptyStringValue(): void
    {
        $this->cache->set('empty', '');
        $this->assertEquals('', $this->cache->get('empty'));
    }

    public function testDeleteNonExistentKey(): void
    {
        $result = $this->cache->delete('does_not_exist');
        $this->assertTrue($result);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('overwrite', 'original');
        $this->assertEquals('original', $this->cache->get('overwrite'));

        $this->cache->set('overwrite', 'updated');
        $this->assertEquals('updated', $this->cache->get('overwrite'));
    }

    public function testSetWithZeroTtl(): void
    {
        $this->cache->set('zero_ttl', 'value', 0);

        sleep(1);

        $this->assertTrue($this->cache->has('zero_ttl'));
    }

    public function testSetWithNegativeTtl(): void
    {
        $this->cache->set('neg_ttl', 'value', -10);

        sleep(1);

        $this->assertTrue($this->cache->has('neg_ttl'));
    }

    public function testClearWithoutNamespace(): void
    {
        // Create cache without namespace
        $cache = new CacheApcu();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertTrue($cache->clear());
    }

    public function testLargeDataStorage(): void
    {
        // Test with large data (1MB string)
        $largeData = str_repeat('x', 1024 * 1024);

        $this->cache->set('large', $largeData);
        $result = $this->cache->get('large');

        $this->assertEquals($largeData, $result);
    }
}
