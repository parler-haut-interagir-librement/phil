# phil/idempotency

**Each operation run once per idempotency key, whatever the retries.**

phil/idempotency remembers the key of an intention — the `Idempotency-Key` header of an API request, the identifier
a message carries, the event of a payment webhook, the token of a form — and runs the operation once. A retry with
the same payload gets the first answer again; a key reused with another payload is refused; a retry that arrives
while the first run is still going is told to come back after the seconds left on its reservation. A run that fails
lets the same payload run again.

Each record is in one of three states: in progress, completed or failed. The in-progress state expires on its own,
so that a process killed in the middle of a run never keeps its key. Only the SHA-256 fingerprint of the payload is
kept, never the payload.

It is a plain PHP library. The stores are a port, with an adapter for Doctrine DBAL — one table, an atomic insert —
and one for any PSR-6 cache pool, made atomic by the Symfony Lock component. A Messenger middleware handles a
received message once per key. In a Symfony application, `phil/api-contract-bundle` brings `#[Idempotent]`
controllers on top of it.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and optional dependencies. |
| [The rule](doc/rule.md) | Keys, scopes, fingerprints, the three states and the four outcomes. |
| [The guard](doc/guard.md) | `IdempotencyGuard`: `run()`, `begin()`, `complete()`, `fail()`, and how to use them for an API, a webhook and a form. |
| [Stores](doc/stores.md) | The DBAL table and the cache pool under a lock: atomicity, expiry, purge. |
| [Messenger](doc/messenger.md) | The middleware, its keys, and what it does with a duplicate. |
| [Exceptions](doc/exceptions.md) | What the library throws, and the HTTP answer of each. |
| [Extension points](doc/extension-points.md) | A store of your own, and the contract test it can extend. |
| [Testing](doc/testing.md) | What the suite pins, the concurrency test and the fuzz target. |
| [Reference](doc/reference.md) | Every public type. |

## Quick start

```php
use Phil\Idempotency\{IdempotencyGuard, IdempotencyKey, IdempotencyScope, PayloadFingerprint, StoredResponse};
use Phil\Idempotency\Store\DbalIdempotencyStore;

$guard = new IdempotencyGuard(new DbalIdempotencyStore($connection, $clock), $clock, retention: 86_400);

$run = $guard->run(
    IdempotencyScope::of('client:42'),
    IdempotencyKey::fromHeader($request->headers->get(IdempotencyKey::HEADER)),
    PayloadFingerprint::ofRequest($request->getMethod(), $request->getRequestUri(), $request->getContent()),
    static fn (): StoredResponse => StoredResponse::of(201, ['Location' => ['/orders/7']], $orders->place($request)),
);
// $run->replayed: true when the stored answer was returned without running the operation.
```

## Requirements

PHP 8.5 or later, `psr/clock` 1 and `symfony/uid` 8.2. The DBAL store needs `doctrine/dbal` 4.5; the cache store
`psr/cache` 3 and `symfony/lock` 8.2; the middleware `symfony/messenger` 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
