# Testing

```bash
vendor/bin/phpunit packages/idempotency/tests
```

- **Unit** covers each value, the decision table, the guard against a recording store, the Messenger middleware, and
  the contract of `IdempotencyStore` run against both adapters: SQLite in memory for the table, an array pool and an
  in-memory lock store for the cache. The material of the fingerprint is pinned.
- **Integration** starts eight PHP processes that ask for the same key at the same moment, once through a SQLite
  file and once through a filesystem pool under file locks: exactly one proceeds, and the seven others are told a
  run is in progress.
- **Fuzz**: the `idempotency-key` target feeds the reader of the header arbitrary text; whatever it reads must read
  back to the same key from its quoted form, and it throws nothing but the library's exceptions.

```bash
composer fuzz -- idempotency-key --budget=60
```

## In your tests

Give the guard a `Symfony\Component\Clock\MockClock` and move it to cross an expiry. The DBAL store runs on SQLite in
memory once `IdempotencyTable::configure()` has created its table; the cache store on an `ArrayAdapter` and an
`InMemoryStore` of locks.
