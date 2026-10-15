<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Tests\Integration\Adapter;

use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisAdapter\Cache\Tests\Integration\DatabaseHelper;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;

/**
 * Both connection forms of the connection-backed adapters: a ready handle and a
 * lazy provider Closure that is called on first access only, exactly once.
 */
class CacheLazyConnectionTest extends TestCase
{
    // ---- CacheDatabase ----------------------------------------------------

    public function testDatabaseObjectFormStillWorks(): void
    {
        $pdo = DatabaseHelper::createSqliteDatabase();
        $cache = new CacheDatabase($pdo, namespace: 'obj');

        $this->assertTrue($cache->set('k', 'v'));
        $this->assertSame('v', $cache->get('k'));
        $this->assertSame($pdo, $cache->getConnection());
    }

    public function testDatabaseClosureFormWorks(): void
    {
        $cache = new CacheDatabase(fn(): PDO => DatabaseHelper::createSqliteDatabase(), namespace: 'lazy');

        $this->assertTrue($cache->set('k', 'v'));
        $this->assertSame('v', $cache->get('k'));
        $this->assertTrue($cache->has('k'));
        $this->assertTrue($cache->delete('k'));
        $this->assertFalse($cache->has('k'));
    }

    public function testDatabaseConstructionDoesNotCallProvider(): void
    {
        $calls = 0;
        new CacheDatabase(function () use (&$calls): PDO {
            $calls++;
            return DatabaseHelper::createSqliteDatabase();
        });

        $this->assertSame(0, $calls);
    }

    public function testDatabaseProviderIsCalledOnceOnFirstAccessAndMemoized(): void
    {
        $calls = 0;
        $cache = new CacheDatabase(function () use (&$calls): PDO {
            $calls++;
            return DatabaseHelper::createSqliteDatabase();
        });

        $this->assertSame(0, $calls);
        $cache->set('a', 1);
        $this->assertSame(1, $calls);
        $cache->get('a');
        $cache->has('a');
        $cache->delete('a');
        $cache->clear();
        $cache->cleanExpired();
        $connection = $cache->getConnection();
        $this->assertSame(1, $calls);
        $this->assertSame($connection, $cache->getConnection());
    }

    public function testDatabaseGetConnectionResolvesProvider(): void
    {
        $calls = 0;
        $pdo = DatabaseHelper::createSqliteDatabase();
        $cache = new CacheDatabase(function () use (&$calls, $pdo): PDO {
            $calls++;
            return $pdo;
        });

        $this->assertSame(0, $calls);
        $this->assertSame($pdo, $cache->getConnection());
        $this->assertSame(1, $calls);
    }

    public function testDatabaseProviderExceptionPassesThroughUnchangedOnFirstAccess(): void
    {
        $error = new PDOException('connect failed');
        $cache = new CacheDatabase(function () use ($error): PDO {
            throw $error;
        });

        try {
            $cache->get('k');
            $this->fail('Provider exception must not be swallowed');
        } catch (PDOException $e) {
            $this->assertSame($error, $e);
        }
    }

    // ---- CacheRedis -------------------------------------------------------

    public function testRedisObjectFormStillWorks(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->once())->method('get')->willReturn('"v"');

        $cache = new CacheRedis($redis, 'obj');

        $this->assertSame('v', $cache->get('k'));
        $this->assertSame($redis, $cache->getConnection());
    }

    public function testRedisClosureFormWorks(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->once())->method('set')->willReturn(true);
        $redis->expects($this->once())->method('get')->willReturn('"v"');
        $redis->expects($this->once())->method('exists')->willReturn(1);
        $redis->expects($this->once())->method('del')->willReturn(1);

        $cache = new CacheRedis(fn(): Redis => $redis, 'lazy');

        $this->assertTrue($cache->set('k', 'v'));
        $this->assertSame('v', $cache->get('k'));
        $this->assertTrue($cache->has('k'));
        $this->assertTrue($cache->delete('k'));
    }

    public function testRedisConstructionDoesNotCallProvider(): void
    {
        $calls = 0;
        new CacheRedis(function () use (&$calls): Redis {
            $calls++;
            return $this->createMock(Redis::class);
        });

        $this->assertSame(0, $calls);
    }

    public function testRedisProviderIsCalledOnceOnFirstAccessAndMemoized(): void
    {
        $calls = 0;
        $redis = $this->createMock(Redis::class);
        $redis->method('get')->willReturn(false);
        $redis->method('set')->willReturn(true);
        $redis->method('exists')->willReturn(0);
        $redis->method('del')->willReturn(0);
        $redis->method('flushDB')->willReturn(true);

        $cache = new CacheRedis(function () use (&$calls, $redis): Redis {
            $calls++;
            return $redis;
        });

        $this->assertSame(0, $calls);
        $cache->set('a', 1);
        $this->assertSame(1, $calls);
        $cache->get('a');
        $cache->has('a');
        $cache->delete('a');
        $cache->clear();
        $this->assertSame($redis, $cache->getConnection());
        $this->assertSame(1, $calls);
    }

    public function testRedisGetConnectionResolvesProvider(): void
    {
        $calls = 0;
        $redis = $this->createMock(Redis::class);
        $cache = new CacheRedis(function () use (&$calls, $redis): Redis {
            $calls++;
            return $redis;
        });

        $this->assertSame(0, $calls);
        $this->assertSame($redis, $cache->getConnection());
        $this->assertSame(1, $calls);
    }

    public function testRedisProviderExceptionPassesThroughUnchangedOnFirstAccess(): void
    {
        $error = new RuntimeException('redis unreachable');
        $cache = new CacheRedis(function () use ($error): Redis {
            throw $error;
        });

        try {
            $cache->get('k');
            $this->fail('Provider exception must not be swallowed');
        } catch (RuntimeException $e) {
            $this->assertSame($error, $e);
        }
    }

    public function testRedisClosureFormAgainstRealServer(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available');
        }

        $host = getenv('REDIS_HOST') ?: (gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1');
        $calls = 0;
        $cache = new CacheRedis(function () use (&$calls, $host): Redis {
            $calls++;
            $redis = new Redis();
            $redis->connect($host, 6379);
            $redis->select(15);
            return $redis;
        }, 'lazy_real_');

        $this->assertSame(0, $calls);
        $this->assertTrue($cache->set('k', ['a' => 1]));
        $this->assertSame(['a' => 1], $cache->get('k'));
        $cache->clear();
        $this->assertSame(1, $calls);
    }
}
