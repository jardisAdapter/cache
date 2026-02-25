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
- **Named Layer Access** — Target specific cache layers via `layer()` for selective operations
- **PSR-16 Compliant** — Standard Simple Cache interface, works with any PSR-16 tool
- **Multiple Backends** — Memory, APCu, Redis, and Database adapters included
- **Namespace Isolation** — Prevent key collisions with namespace prefixes
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

// Multi-layer cache with named layers (PHP 8 named arguments)
$cache = new Cache(
    memory: new CacheMemory(),          // L1: In-memory (fastest)
    redis:  new CacheRedis($redis)      // L2: Redis (persistent)
);

// PSR-16: operates on all layers
$cache->set('user:123', $userData, 3600);
$user = $cache->get('user:123');
$cache->delete('user:123');

// Targeted: operate on a specific layer only
$cache->layer(CacheRedis::LAYER_NAME)->set('session:abc', $data, 1800);
$cache->layer(CacheMemory::LAYER_NAME)->delete('temp:key');
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
