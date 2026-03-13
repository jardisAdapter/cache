# Jardis Cache

![Build Status](https://github.com/jardisAdapter/cache/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-success.svg)](phpstan.neon)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-orange.svg)](phpcs.xml)
[![PSR-16](https://img.shields.io/badge/cache-PSR--16%20v3.0-brightgreen.svg)](https://www.php-fig.org/psr/psr-16/)
[![Coverage](https://img.shields.io/badge/coverage->82%25-brightgreen)](https://github.com/jardisAdapter/cache)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

A powerful, PSR-16 compliant multi-layer caching library for PHP 8.2+ that provides blazing-fast performance through intelligent cache layering and automatic write-through propagation. Chain multiple cache backends (Memory, APCu, Redis, Database) with automatic fallback and write-through population.

---

## Features

- **Multi-Layer Caching** — Chain of Responsibility pattern for cascading cache lookups (L1 → L2 → L3)
- **Automatic Write-Through** — Cache miss in L1? Automatically populated from L2/L3
- **Purpose-Built Caches** — Build separate Cache instances for different use cases
- **PSR-16 Compliant** — Standard Simple Cache interface, works with any PSR-16 tool
- **Multiple Backends** — Memory, APCu, Redis, Database, and Null adapters included
- **Immutable After Construction** — All layers and namespace set via constructor, no runtime changes
- **Null Object Pattern** — Cache works immediately without configuration (no-op when no layers provided)
- **Namespace Isolation** — Each adapter manages its own namespace via constructor
- **TTL Support** — Seconds or DateInterval for flexible expiration
- **Zero Dependencies** — Only PSR interfaces required

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
use JardisAdapter\Cache\Adapter\CacheDatabase;

// Fast request cache (Memory + APCu)
$requestCache = new Cache([new CacheMemory('request'), new CacheApcu('request')]);

// Session cache (Redis only)
$sessionCache = new Cache([new CacheRedis($redis, 'session')]);

// Persistent cache (Redis + Database)
$persistentCache = new Cache([new CacheRedis($redis, 'persistent'), new CacheDatabase($pdo, namespace: 'persistent')]);

// PSR-16: operates on all layers
$sessionCache->set('session:abc', $data, 1800);
$requestCache->get('product:42');
```

### Namespace Isolation

```php
// Namespace set on each adapter via constructor
$sessionCache = new Cache([new CacheRedis($redis, 'session')]);
$productCache = new Cache([new CacheRedis($redis, 'product')]);  // same Redis, isolated keys

$sessionCache->set('key', 'session_data');
$productCache->set('key', 'product_data');   // no collision
```

### Standalone Adapter

```php
// Namespace via constructor — immutable after creation
$redis = new CacheRedis($redis, 'myapp');
$redis->set('key', 'value');
```

### Safe Default — No Configuration Required

```php
// Works immediately — internal NullCache handles all operations
$cache = new Cache();
$cache->set('key', 'value');   // no-op, returns true
$cache->get('key');            // returns null

// With layers — NullCache is not used
$cache = new Cache([new CacheMemory()]);
$cache->set('key', 'value');   // now stored in memory
```

## Documentation

Full documentation, examples and API reference:

**[jardis.io/docs/adapter/cache](https://jardis.io/docs/adapter/cache)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category    | Packages                                         |
|-------------|--------------------------------------------------|
| **Core**    | Domain, Kernel, Data, Workflow                   |
| **Adapter** | Cache, Logger, Messaging, DbConnection           |
| **Support** | DotEnv, DbQuery, Validation, Factory, ClassVersion |
| **Tools**   | Builder, DbSchema                                |

**[Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
