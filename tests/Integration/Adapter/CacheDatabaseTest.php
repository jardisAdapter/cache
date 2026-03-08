<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use DateInterval;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Tests\Integration\DatabaseHelper;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CacheDatabase implementation using SQLite.
 */
class CacheDatabaseTest extends TestCase
{
    private PDO $pdo;
    private CacheDatabase $cache;

    protected function setUp(): void
    {
        $this->pdo = DatabaseHelper::createSqliteDatabase();
        $this->cache = new CacheDatabase($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
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
        $cache1 = new CacheDatabase($this->pdo, 'namespace1');
        $cache2 = new CacheDatabase($this->pdo, 'namespace2');

        $cache1->set('key', 'value1');
        $cache2->set('key', 'value2');

        $this->assertSame('value1', $cache1->get('key'));
        $this->assertSame('value2', $cache2->get('key'));

        // Cleanup
        $cache1->clear();
        $cache2->clear();
    }

    public function testCleanExpired(): void
    {
        $this->cache->set('key1', 'value1', 1);
        $this->cache->set('key2', 'value2'); // No TTL

        sleep(2);

        $this->assertTrue($this->cache->cleanExpired());
        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
    }

    public function testCustomTableNames(): void
    {
        // Create custom table
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS custom_cache (
                custom_key TEXT PRIMARY KEY,
                custom_value TEXT NOT NULL,
                custom_expires INTEGER
            )
        ');

        // Create index for efficient expiration cleanup
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_custom_cache_expires ON custom_cache(custom_expires)');

        $cache = new CacheDatabase(
            $this->pdo,
            null,
            'custom_cache',
            'custom_key',
            'custom_value',
            'custom_expires'
        );

        $this->assertTrue($cache->set('key1', 'value1'));
        $this->assertSame('value1', $cache->get('key1'));

        // Cleanup
        $cache->clear();
    }

    public function testCleanExpiredWithNoExpiredEntries(): void
    {
        // Set entries without TTL
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        // Clean should succeed but remove nothing
        $this->assertTrue($this->cache->cleanExpired());
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
    }

    public function testCleanExpiredWithMixedEntries(): void
    {
        // Set some with TTL, some without
        $this->cache->set('expired1', 'value1', 1);
        $this->cache->set('expired2', 'value2', 1);
        $this->cache->set('permanent1', 'value3');
        $this->cache->set('permanent2', 'value4');
        $this->cache->set('future', 'value5', 3600); // Will not expire

        sleep(2);

        $this->assertTrue($this->cache->cleanExpired());

        // Expired entries should be gone
        $this->assertFalse($this->cache->has('expired1'));
        $this->assertFalse($this->cache->has('expired2'));

        // Permanent and future entries should remain
        $this->assertTrue($this->cache->has('permanent1'));
        $this->assertTrue($this->cache->has('permanent2'));
        $this->assertTrue($this->cache->has('future'));
    }

    public function testLargeDataStorage(): void
    {
        // Test with large data (1MB string)
        $largeData = str_repeat('x', 1024 * 1024);

        $this->cache->set('large', $largeData);
        $result = $this->cache->get('large');

        $this->assertEquals($largeData, $result);
    }

    public function testMultipleNamespacesConcurrently(): void
    {
        $cache1 = new CacheDatabase($this->pdo, 'app1_');
        $cache2 = new CacheDatabase($this->pdo, 'app2_');

        // Set same keys in different namespaces
        $cache1->set('config', ['setting' => 'value1']);
        $cache2->set('config', ['setting' => 'value2']);

        // Verify isolation
        $result1 = $cache1->get('config');
        $result2 = $cache2->get('config');

        $this->assertEquals('value1', $result1['setting']);
        $this->assertEquals('value2', $result2['setting']);

        // Clean up
        $cache1->clear();
        $cache2->clear();
    }

    public function testSetWithZeroTtl(): void
    {
        // Zero TTL should not set expiration
        $this->cache->set('zero_ttl', 'value', 0);

        sleep(1);

        // Should still exist
        $this->assertTrue($this->cache->has('zero_ttl'));
    }

    public function testSetWithNegativeTtl(): void
    {
        // Negative TTL should not set expiration
        $this->cache->set('negative_ttl', 'value', -10);

        sleep(1);

        // Should still exist
        $this->assertTrue($this->cache->has('negative_ttl'));
    }

    public function testDatabaseTransactionRollback(): void
    {
        // Start a transaction
        $this->pdo->beginTransaction();

        $this->cache->set('transaction_key', 'value');
        $this->assertTrue($this->cache->has('transaction_key'));

        // Rollback
        $this->pdo->rollBack();

        // Key should not exist after rollback
        $this->assertFalse($this->cache->has('transaction_key'));
    }

    public function testDatabaseTransactionCommit(): void
    {
        // Start a transaction
        $this->pdo->beginTransaction();

        $this->cache->set('commit_key', 'value');

        // Commit
        $this->pdo->commit();

        // Key should exist after commit
        $this->assertTrue($this->cache->has('commit_key'));
        $this->assertEquals('value', $this->cache->get('commit_key'));
    }

    public function testGetExpiredKeyReturnsDefault(): void
    {
        $this->cache->set('expired', 'value', 1);

        sleep(2);

        // Should return default value
        $result = $this->cache->get('expired', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'multi_ttl1' => 'value1',
            'multi_ttl2' => 'value2',
        ];

        $this->assertTrue($this->cache->setMultiple($values, 1));
        $this->assertTrue($this->cache->has('multi_ttl1'));
        $this->assertTrue($this->cache->has('multi_ttl2'));

        sleep(2);

        $this->assertFalse($this->cache->has('multi_ttl1'));
        $this->assertFalse($this->cache->has('multi_ttl2'));
    }

    public function testSetWithNullTtl(): void
    {
        $this->cache->set('null_ttl', 'value', null);

        sleep(1);

        $this->assertTrue($this->cache->has('null_ttl'));
        $this->assertEquals('value', $this->cache->get('null_ttl'));
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

    public function testGetMultipleWithMixedResults(): void
    {
        $this->cache->set('exists1', 'value1');
        $this->cache->set('exists2', 'value2');

        $result = $this->cache->getMultiple(
            ['exists1', 'missing', 'exists2', 'also_missing'],
            'default_val'
        );

        $this->assertEquals('value1', $result['exists1']);
        $this->assertEquals('value2', $result['exists2']);
        $this->assertEquals('default_val', $result['missing']);
        $this->assertEquals('default_val', $result['also_missing']);
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

    public function testClearWithoutNamespace(): void
    {
        // Create cache without namespace
        $cache = new CacheDatabase($this->pdo);

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function testCleanExpiredWithEmptyCache(): void
    {
        // Clean expired on empty cache should succeed
        $this->cache->clear();
        $this->assertTrue($this->cache->cleanExpired());
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

    public function testUnicodeValues(): void
    {
        $unicode = 'Hello 世界 🌍 Привет';
        $this->cache->set('unicode', $unicode);
        $this->assertEquals($unicode, $this->cache->get('unicode'));
    }

    public function testArrayWithNestedObjects(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'meta' => (object)['created' => '2024-01-01'],
            ],
        ];

        $this->cache->set('nested', $data);
        $result = $this->cache->get('nested');

        $this->assertEquals('John', $result['user']['name']);
        // JSON encoding converts objects to arrays, so meta becomes an array
        $this->assertEquals('2024-01-01', $result['user']['meta']['created']);
    }

    public function testGetConnectionReturnsPdoInstance(): void
    {
        $connection = $this->cache->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
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

        $this->assertSame($this->pdo, $connection);
    }

    public function testGetConnectionAllowsDirectDatabaseOperations(): void
    {
        $connection = $this->cache->getConnection();

        // Verify we can execute direct queries on the connection
        $stmt = $connection->query("SELECT sqlite_version()");
        $result = $stmt->fetchColumn();

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }
}
