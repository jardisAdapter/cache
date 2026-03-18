<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use JardisAdapter\Cache\Adapter\CacheNull;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CacheNull (Null Object Pattern).
 */
class CacheNullTest extends TestCase
{
    private CacheNull $cache;

    protected function setUp(): void
    {
        $this->cache = new CacheNull();
    }

    public function testGetAlwaysReturnsDefault(): void
    {
        $this->assertNull($this->cache->get('any_key'));
        $this->assertSame('default', $this->cache->get('any_key', 'default'));
        $this->assertSame(42, $this->cache->get('any_key', 42));
    }

    public function testSetAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->cache->set('key', 'value'));
        $this->assertTrue($this->cache->set('key', 'value', 3600));
    }

    public function testDeleteAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->cache->delete('key'));
    }

    public function testClearAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->cache->clear());
    }

    public function testHasAlwaysReturnsFalse(): void
    {
        $this->assertFalse($this->cache->has('any_key'));
    }

    public function testSetDoesNotStore(): void
    {
        $this->cache->set('key', 'value');

        $this->assertNull($this->cache->get('key'));
        $this->assertFalse($this->cache->has('key'));
    }

    public function testGetMultipleReturnsDefaults(): void
    {
        $result = $this->cache->getMultiple(['key1', 'key2'], 'default');

        $this->assertSame('default', $result['key1']);
        $this->assertSame('default', $result['key2']);
    }

    public function testSetMultipleReturnsTrue(): void
    {
        $this->assertTrue($this->cache->setMultiple(['key1' => 'v1', 'key2' => 'v2']));
    }

    public function testDeleteMultipleReturnsTrue(): void
    {
        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));
    }

    public function testWithNamespace(): void
    {
        $cache = new CacheNull('my_namespace');

        $this->assertTrue($cache->set('key', 'value'));
        $this->assertNull($cache->get('key'));
        $this->assertFalse($cache->has('key'));
    }
}
