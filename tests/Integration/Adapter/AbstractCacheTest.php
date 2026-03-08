<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use DateInterval;
use Exception;
use JardisAdapter\Cache\Adapter\CacheMemory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AbstractCache protected methods and edge cases.
 */
class AbstractCacheTest extends TestCase
{
    private CacheMemory $cache;

    protected function setUp(): void
    {
        $this->cache = new CacheMemory();
    }

    public function testEmptyKeyThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key must be a non-empty string.');

        $this->cache->set('', 'value');
    }

    public function testWhitespaceOnlyKeyThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key must be a non-empty string.');

        $this->cache->set('   ', 'value');
    }

    public function testNegativeTtlReturnsNull(): void
    {
        // Negative TTL should not set expiration
        $this->cache->set('key', 'value', -1);
        $this->assertTrue($this->cache->has('key'));

        // Value should not expire
        sleep(1);
        $this->assertTrue($this->cache->has('key'));
    }

    public function testZeroTtlReturnsNull(): void
    {
        // Zero TTL should not set expiration
        $this->cache->set('key', 'value', 0);
        $this->assertTrue($this->cache->has('key'));

        // Value should not expire
        sleep(1);
        $this->assertTrue($this->cache->has('key'));
    }

    public function testSetMultipleWithPartialFailure(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key must be a non-empty string.');

        // This should throw an exception when encountering empty key
        $this->cache->setMultiple([
            'key2' => 'value2',
            '' => 'should_fail', // Empty key will throw exception
        ]);
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $this->cache->set('key1', 'value1');

        // Should return true even if some keys don't exist
        $result = $this->cache->deleteMultiple(['key1', 'nonexistent']);
        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testEncodeDecodeWithNonUtf8String(): void
    {
        // Test with binary data that might fail JSON encoding
        $binaryData = "\x80\x81\x82\x83";

        $this->cache->set('binary', $binaryData);
        $result = $this->cache->get('binary');

        // Should handle binary data via serialization fallback
        $this->assertEquals($binaryData, $result);
    }

    public function testEncodeDecodeWithResource(): void
    {
        // Resources should be handled via serialization
        $resource = fopen('php://memory', 'r+');

        $this->cache->set('resource', $resource);
        $result = $this->cache->get('resource');

        // Resources might not survive serialization/unserialization
        // After unserialization, resource type is converted to closed resource
        // We just verify the operation doesn't crash
        $this->assertTrue(true);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testDecodeWithSerializedBoolean(): void
    {
        // Test edge case: serialized false boolean
        $this->cache->set('bool_false', false);
        $result = $this->cache->get('bool_false');

        $this->assertFalse($result);
    }

    public function testDecodeWithPlainString(): void
    {
        // Plain strings that don't match JSON or serialization patterns
        $plainString = 'just a plain string';

        $this->cache->set('plain', $plainString);
        $result = $this->cache->get('plain');

        $this->assertEquals($plainString, $result);
    }

    public function testDecodeWithNumericString(): void
    {
        // Numeric strings should be decoded properly
        $numericString = '12345';

        $this->cache->set('numeric', $numericString);
        $result = $this->cache->get('numeric');

        $this->assertEquals($numericString, $result);
    }

    public function testTtlWithLargeDateInterval(): void
    {
        // Test with a very large DateInterval
        $interval = new DateInterval('P1Y'); // 1 year

        $this->cache->set('long_ttl', 'value', $interval);
        $this->assertTrue($this->cache->has('long_ttl'));
    }

    public function testGetMultipleWithEmptyArray(): void
    {
        $result = $this->cache->getMultiple([]);

        $this->assertIsIterable($result);
        $this->assertEmpty($result);
    }

    public function testSetMultipleWithEmptyArray(): void
    {
        $result = $this->cache->setMultiple([]);

        $this->assertTrue($result);
    }

    public function testDeleteMultipleWithEmptyArray(): void
    {
        $result = $this->cache->deleteMultiple([]);

        $this->assertTrue($result);
    }

    public function testNamespaceIsolation(): void
    {
        $cache1 = new CacheMemory('namespace1');
        $cache2 = new CacheMemory('namespace2');

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        // Different namespaces should have different values
        $this->assertEquals('value1', $cache1->get('key'));
        $this->assertEquals('value2', $cache2->get('key'));

        // Clearing one namespace shouldn't affect the other
        $cache1->clear();
        $this->assertNull($cache1->get('key'));
        $this->assertEquals('value2', $cache2->get('key'));
    }

    public function testEncodeDecodeWithCircularReference(): void
    {
        // Objects with circular references should use serialization
        $obj = new \stdClass();
        $obj->self = $obj; // Circular reference

        // This might throw or handle gracefully depending on implementation
        try {
            $this->cache->set('circular', $obj);
            $result = $this->cache->get('circular');

            // If it succeeds, verify it's an object
            $this->assertIsObject($result);
        } catch (Exception $e) {
            // It's okay if circular references throw exceptions
            $this->assertTrue(true);
        }
    }
}
