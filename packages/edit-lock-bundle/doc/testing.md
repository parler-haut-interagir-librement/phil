# Testing

## The bundle's tests

`tests/Unit` covers the keys, holders and leases and their mapping, `EditLocks` — acquiring, renewing, waiting,
expiry, the whole and its parts, heartbeats, takeover, the policy, leaving, purging, races and claims the store gave
up tried again then given up —, `BlockSaves`, the same contract on both repositories (the Doctrine one on SQLite in
memory, its tables created from the mapping), the Doctrine claims in their transaction, undone when they fail, two
concurrent claims on a SQLite file opened twice — the whole and a part claimed while the other is in the middle of
its claim, which waits, gives up, then finds the first lease —, the Turbo Streams and the broadcaster on a recording
hub and an unreachable one, every answer of the endpoints and the CSRF tokens they accept and refuse, `ClientConfig`,
and the bundle's configuration, services and prepended settings.

`tests/Integration` plays the same concurrent claims on MySQL and PostgreSQL, each skipped unless
`PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` points at a server; `php vendor/bin/phil-dev services up` starts
both, and `php vendor/bin/phil-dev services test` runs the suite against them.

`tests/Functional` boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, MercureBundle, `phil/ujo-bundle` and
the bundle, and plays two writers on one page through the routes: leases, a save, a heartbeat, presence, a refused
then an allowed takeover, a removal, leaving, each request with its CSRF token and cookie, requests without them
refused; what the recording hub received is checked stream by stream.
`MercureHubTest` publishes through a real hub and reads the update back as a subscriber; it is skipped unless a hub
answers at `MERCURE_URL`, by default `http://127.0.0.1:3999/.well-known/mercure`:

```sh
docker run -d -p 3999:80 -e SERVER_NAME=':80' \
    -e MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
    -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' dunglas/mercure
```

Installed on its own, where the event dispatcher is a development requirement, the functional suite is skipped.

`tests/js` runs the pure modules under Node's test runner: lease requests and their CSRF token, snapshots and their
changes, presence and the roster, the Turbo Streams, the labels.

From the monorepo root, then from the bundle:

```sh
vendor/bin/phpunit packages/edit-lock-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/edit-lock-bundle
```

Every line of `src/` is held by the mutation bar of the monorepo: each mutant is killed.

## Checks in a browser

The controller is checked by hand with two writers — two browser profiles, or two host names of the same site so that
each keeps its own session — on a page with Stimulus, the block editor, the controller, the routes, a hub and a block
store. Each check states what to do and what must happen.

| Area | Do | Expect |
|---|---|---|
| Lease | A types in a paragraph | B sees A in the roster, the paragraph outlined with A's name and closed, A's text appearing as A types |
| Takeover | B, allowed to, clicks *Take over* | B holds the block; A's copy of it is closed with B's name and A is told it was taken over |
| New block | A presses Enter at the end of a paragraph and types | B sees the new paragraph appear right after it, held by A |
| Late writer | A third writer opens the page | The page shows every saved block, from the block store |
| Leaving | B closes the page | B leaves the roster and B's block opens for A |
| Without a hub | Turn Mercure off and reload | Locks still show, at the next heartbeat; saves reach the others when they reload |

## Testing an application that uses it

Configure `store: memory` and `mercure.enabled: false` in the test environment, or keep Doctrine on the test
database and give `mercure.hub` a hub that records, as the bundle's functional kernel does. In unit tests, build the
pieces directly:

```php
$locks = new EditLocks(new InMemoryLeaseRepository(), new MockClock(), 30, new WritersPolicy());
$locks->acquire(LeaseKey::of('article:42', 'aB3x'), Holder::of('7', 'Ada'));
```
