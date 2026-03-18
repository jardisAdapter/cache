# Jardis Cache

![Build Status](https://github.com/jardisAdapter/cache/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![PSR-16](https://img.shields.io/badge/Cache-PSR--16-brightgreen.svg)](https://www.php-fig.org/psr/psr-16/)
[![Coverage](https://img.shields.io/badge/Coverage-84.51%25-green.svg)](https://github.com/jardisAdapter/cache)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

PSR-16 multi-layer caching engine. Chain Memory, APCu, Redis, and Database backends in a single `Cache` instance. On a cache miss in a fast layer, the value is automatically backfilled from the next slower layer — so subsequent reads hit the fastest backend available. Writes propagate to all configured layers simultaneously.

---

## Features

- **Multi-Layer Chain** — Stack any number of backends; reads backfill upper layers automatically
- **5 Backends** — `CacheMemory`, `CacheApcu`, `CacheRedis`, `CacheDatabase`, `CacheNull`
- **PSR-16** — Full `Psr\SimpleCache\CacheInterface` implementation on every layer
- **Namespace Isolation** — Each layer instance carries its own namespace prefix
- **Immutable After Construction** — All layers set via constructor; no mutation at runtime
- **Null Object Pattern** — Empty `Cache([])` degrades gracefully to a no-op `CacheNull`
- **TTL Support** — Integer seconds or `DateInterval` on every `set()` / `setMultiple()`
- **Zero Dependencies** — No third-party packages required beyond PSR interfaces

---

## Installation

```bash
composer require jardisadapter/cache
```

## Quick Start

```php
use JardisAdapter\Cache\Cache;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheRedis;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Two-layer cache: L1 = in-process memory, L2 = Redis
$cache = new Cache([
    new CacheMemory('myapp'),
    new CacheRedis($redis, 'myapp'),
]);

$cache->set('user:42', $userData, ttl: 300);
$user = $cache->get('user:42');
```

## Advanced Usage

```php
use JardisAdapter\Cache\Cache;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheApcu;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisAdapter\Cache\Adapter\CacheDatabase;

// Four-layer cascade: L1 memory → L2 APCu → L3 Redis → L4 database
// A miss at L1 checks L2, then L3, then L4.
// When found, the value is written back into all faster layers automatically.
$cache = new Cache([
    new CacheMemory('orders'),
    new CacheApcu('orders'),
    new CacheRedis($redis, 'orders'),
    new CacheDatabase($pdo, namespace: 'orders'),
]);

// Bulk operations — all layers updated in one call
$cache->setMultiple([
    'order:101' => $order101,
    'order:102' => $order102,
], ttl: 600);

$orders = $cache->getMultiple(['order:101', 'order:102']);

// Invalidate a key across all layers
$cache->delete('order:101');

// Expire stale database entries explicitly
$dbLayer = new CacheDatabase($pdo, namespace: 'orders');
$dbLayer->cleanExpired();
```

## Documentation

Full documentation, guides, and API reference:

**[jardis.io/docs/adapter/cache](https://jardis.io/docs/adapter/cache)**

## License

This package is licensed under the [PolyForm Shield License 1.0.0](LICENSE.md). Free for all use except building competing frameworks or developer tooling.

---

**[Jardis](https://jardis.io)** · [Documentation](https://jardis.io/docs) · [Headgent](https://headgent.com)
