<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use DateInterval;
use JardisAdapter\Cache\Adapter\CacheMemory;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CacheMemory implementation.
 */
class CacheMemoryTest extends TestCase
{
    private CacheMemory $cache;

    protected function setUp(): void
    {
        $this->cache = new CacheMemory();
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

    public function testNamespace(): void
    {
        $cache1 = new CacheMemory('namespace1');
        $cache2 = new CacheMemory('namespace2');

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $this->assertSame('value1', $cache1->get('key'));
        $this->assertSame('value2', $cache2->get('key'));
    }
}
