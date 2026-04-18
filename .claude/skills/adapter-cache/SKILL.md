---
name: adapter-cache
description: PSR-16 multi-layer caching, write-through, namespace isolation. Use for CacheService, cache layers.
user-invocable: false
---

# CACHE_COMPONENT_SKILL
> jardisadapter/cache v1.0.0 | NS: `JardisAdapter\Cache` | PSR-16 v3.0 multi-layer | PHP 8.2+

## ARCHITECTURE
```
Cache (Chain of Responsibility, implements CacheInterface)
  Immutable after construction — layers fixed via constructor
  Default internal CacheNull when no layers provided (no-op)
  L1: CacheMemory  (request-scoped)
  L2: CacheApcu / CacheRedis  (shared)
  L3: CacheDatabase  (durable)

get():            L1→L2→L3, stops on first hit, auto-populates faster layers (write-through)
set/delete/clear: applied to ALL layers
has():            true if found in ANY layer
```

## API / SIGNATURES
```php
// PSR-16 v3.0
get(string $key, mixed $default = null): mixed
set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
delete(string $key): bool
has(string $key): bool
clear(): bool
getMultiple(iterable $keys, mixed $default = null): iterable
setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
deleteMultiple(iterable $keys): bool

// Cache class
new Cache(array $caches = [])   // immutable after construction
$cache->getLayers(): array<int, CacheInterface>
```

## ADAPTERS
| Adapter | Constructor | Scope |
|---------|-------------|-------|
| `CacheNull` | `(?string $namespace = null)` | no-op |
| `CacheMemory` | `(?string $namespace = null)` | request |
| `CacheApcu` | `(?string $namespace = null)` | worker (shared FPM) |
| `CacheRedis` | `(Redis $redis, ?string $namespace = null)` | distributed |
| `CacheDatabase` | `(PDO $pdo, ?string $cacheTable, ?string $cacheKeyField, ?string $cacheValueField, ?string $cacheExpiresAt, ?string $namespace = null)` | durable |

`CacheRedis::getConnection(): Redis` / `CacheDatabase::getConnection(): PDO`

## SETUP
```php
use JardisAdapter\Cache\Cache;
use JardisAdapter\Cache\Adapter\{CacheMemory, CacheApcu, CacheRedis, CacheDatabase};

$requestCache   = new Cache([new CacheMemory('request'), new CacheApcu('request')]);
$sessionCache   = new Cache([new CacheRedis($redis, 'session')]);
$persistentCache = new Cache([new CacheRedis($redis, 'persistent'), new CacheDatabase($pdo, namespace: 'persistent')]);

$cache = new Cache();   // internal NullCache, all ops no-op, returns true/default
```

## ABSTRACT CACHE (`JardisAdapter\Cache\Adapter\AbstractCache`)
```php
__construct(?string $namespace = null)  // immutable after construction
namespace(): string       // protected
hash(string $key): string // namespace + sha256(key)
ttl(int|DateInterval|null): ?int   // → absolute Unix-timestamp or null
encode(mixed $value): string       // json_encode, fallback serialize
decode(mixed $value): mixed        // json_decode, fallback unserialize
isExpired(mixed $result): bool     // checks ['ttl'] <= time()
```
Provides default `getMultiple/setMultiple/deleteMultiple` implementations.

## NAMESPACE
- Each adapter manages its own namespace via constructor.
- `Cache` has no namespace parameter.
- Keys: SHA-256 hashed, prefixed with namespace.
- `clear()` only affects keys with matching namespace prefix.

## DATABASE SCHEMA
```sql
CREATE TABLE cache (cache_key TEXT PRIMARY KEY, cache_value TEXT NOT NULL, expires_at INTEGER);
CREATE INDEX idx_cache_expires_at ON cache(expires_at);
```
`$cacheDb->cleanExpired()` — deletes expired rows.

## EXCEPTIONS
| Exception | Trigger |
|-----------|---------|
| `Exception` | Empty/whitespace key in `hash()` |
| `RedisException` | Caught → returns `$default` / `false` |
| `PDOException` | Caught → returns `$default` / `false` |

Graceful degradation: failed ops return `false`.

## LAYER
- Domain: NEVER imports cache.
- Application: inject `CacheInterface`.
- Infrastructure: configure layers via constructor.
- Logging/metrics: Decorator on `CacheInterface` — never inside the package.
