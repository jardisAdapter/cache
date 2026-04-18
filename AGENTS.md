# jardisadapter/cache

PSR-16 multi-layer cache with Chain of Responsibility: `get()` runs L1 → L2 → L3 and auto-populates higher layers on hit (write-through).

## Usage essentials

- **Immutable:** All layers are passed via the constructor (`new Cache([...])`). No `addCache()`, no runtime changes.
- **Purpose-Built:** Build a separate `Cache` instance per use case (e.g. `$requestCache`, `$sessionCache`). No string-based layer handling.
- **`set()`/`delete()`/`clear()` affect ALL layers;** `get()` stops at the first hit and populates backwards.
- **Namespace per adapter, not per cache:** `new CacheRedis($redis, 'myapp')`. `Cache` itself has no namespace — it trusts its layers.
- **Safe Default:** `new Cache()` without layers uses an internal `CacheNull` — all ops are no-op (no bootstrapping needed).
- **Graceful Degradation:** `RedisException`/`PDOException` are caught → returns `$default`/`false`. No exceptions propagate outward.

## Full reference

https://docs.jardis.io/en/adapter/cache
