# Testing

## How the bundle is tested

Unit tests under `tests/Unit/`, namespace `Phil\AuditBundle\Tests\`, PHPUnit 13, `#[CoversClass]` on each test
class. No kernel; the table runs on SQLite in memory.

| Test | What it proves |
|---|---|
| `Domain\Commitment\CommitmentTest` | The construction is `HMAC-SHA256(key = salt, message = value)` with binary output, pinned by RFC 4231 test case 1; a commitment matches its value and salt only; a stored commitment round-trips; anything but 32 bytes is refused |
| `Domain\Commitment\SaltTest` | A generated salt is exactly 16 bytes and two differ; a stored salt of 16 bytes or more is accepted, 15 is refused |
| `Domain\Chain\ChainTest`, `Domain\Chain\FactsTest` | The row hash, pinned line by line with its facts; a change to any field or fact shows; a line break stored before the payload makes a row not intact; every refusal of a fact |
| `Domain\Chain\SiteSegmentTest` | The name of a site segment, its digest, its base, what is not one, and its refusals |
| `Domain\Read\QueryTest` | Each filter of a query, the period in UTC, the order of a cursor, its token and its refusals, a segment's state |
| `Application\SitesTest` | Each tenant chained on its own, a site's restore, a row of another site refused, a tenant held to its own segments, a segment's site |
| `Application\FeedTest` | The facts sent and no payload; places per feed and segment, batches, bounded runs, a failed batch sent again, the handler of `AuditRecorded` |
| `Infrastructure\TenantIsolationTest` | Two tenants and their look-alikes (`ACME`, `acmé`, `Acme`) on a table given a collation that folds case and accents as `utf8mb4_0900_ai_ci` does: the reader and `AuditLog` keep the rows of the tenant, the actor and the record asked for only, pages go on past the rows left out, a row is read by its exact place |
| `Application\TrailTest`, `Application\ReadSideTest` | Recording, anchoring and verifying; the tenant stamped, the notifier told once; pages, history, export and the tenant held on reads; the chain's state |
| `Infrastructure\StorageTest`, `Infrastructure\LogReaderTest` | The table with its facts and indexes; the reader on the table and the scanning reader give the same pages for every query, limit and order |
| `Infrastructure\ExportTest` | JSON Lines to a file and to a stream; OTLP request, body, refusals and redirects never followed on a mock HTTP client, the endpoint read by `phil/url`; the places in their table only moving forward; `rowsAfter()` on the table |
| `Infrastructure\AdaptersTest`, `Infrastructure\CommandsTest`, `PhilAuditBundleTest` | Messenger, tenancy, Security and Workflow adapters; the commands, `--tenant` and `--max`; the wiring of every key, the feed and `ContextPass` |

The two ports the application always writes, `CanonicalSerializer` and `AnchorWriter`, have no test in the bundle:
there is nothing to run until an application implements them.

A functional suite under `tests/Functional/` boots a real kernel, `tests/Functional/App/TestKernel.php`
(FrameworkBundle and the bundle under an empty `phil_audit` block), with its cache in a directory of its own under
the system temporary directory. `BundleRegistrationTest` pins what an application sees of the bundle: the alias, the
keys `config:dump-reference phil_audit` prints, and the build failure an unknown key causes.
It needs no database and no environment variable: `vendor/bin/phpunit packages/audit-bundle/tests/Functional`.

An integration suite under `tests/Integration/` runs the tables of the trail and of the feeds on MySQL 8 and
PostgreSQL 17: `MySqlStorageTest` and `PostgreSqlStorageTest` record a site's rows in UTF-8, read them after a
sequence, page them on the table exactly as the scanning reader does, verify them, refuse a place taken twice, and
feed them with places that never move back; they also check that the server compares the tenant, the actor and the
record's identifier byte by byte, so that `acme`, `ACME`, `acmé` and `Acme` are four tenants. Each reads its server's URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it; from the monorepo root, `phil-dev services up` starts the
servers and `phil-dev services test` runs the suite against them.

### From a monorepo root that installs the bundle by path

```bash
vendor/bin/phpunit packages/audit-bundle/tests
```

### Installed on its own

```bash
cd audit-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

The package ships no `phpunit.xml`. `phil/ddd-bundle` must be resolvable (from a `path` or `vcs` repository until
it is published).

The package ships `composer-dependency-analyser.php` for
[`shipmonk/composer-dependency-analyser`](https://github.com/shipmonk-rnd/composer-dependency-analyser). It has one
deliberate exception: `symfony/config` is declared but imported nowhere, because `AbstractBundle` needs it at load
time (see [installation.md](installation.md)).

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the chain verifier on intact segments of up to 50 000 rows, a
site's segment among them, and on rewritten segments with up to 1 000 lost anchors (`ChainVerifierBench`); a page of
the log read on the table of 50 000 rows, first and after 49 950 rows, whole and for one actor (`LogReaderBench`): a
page after a cursor costs what the first one does; and a batch of 500 facts fed from the table as JSON Lines, first
and after 49 000 rows (`FeedBench`): a batch from deep in a segment costs what the first one does. Run them from the
monorepo root:

```bash
composer bench -- packages/audit-bundle/tests/Benchmark
```

## Fuzzing

Two fuzz targets give inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/<target>`: `cursor-token`, where a
page cursor either writes back to the very token it read or is refused with `InvalidValue`, and `site-segment`, where
a site segment is built or refused, and one built names its own tenant alone, fits the table and gives back its base.
From the monorepo root:

```bash
composer fuzz -- cursor-token site-segment
```

## Testing your adapters

### `CanonicalSerializer`: golden vectors

The serializer is a one-way door, so its test is a set of fixed inputs and the exact bytes expected for each,
committed with the class and never regenerated:

```php
final class LengthPrefixedSerializerV1Test extends TestCase
{
    public function testTheVersionIsFrozen(): void
    {
        self::assertSame('lp-v1', new LengthPrefixedSerializerV1()->version());
    }

    public function testTheBytesAreFrozen(): void
    {
        self::assertSame(
            '1:a3:s781:b1:n1:c2:i3',
            new LengthPrefixedSerializerV1()->serialize(['c' => 3, 'a' => 'x', 'b' => null]),
        );
    }

    public function testFieldOrderDoesNotMatter(): void
    {
        $serializer = new LengthPrefixedSerializerV1();

        self::assertSame($serializer->serialize(['a' => 1, 'b' => 2]), $serializer->serialize(['b' => 2, 'a' => 1]));
    }

    public function testACommitmentIsSerializedLosslessly(): void
    {
        $commitment = Commitment::of(Salt::generate(), 'value');

        self::assertStringContainsString(bin2hex($commitment->bytes), new LengthPrefixedSerializerV1()->serialize(['email' => $commitment->bytes]));
    }
}
```

A failing golden vector means the change must become a new version, not an updated expectation.

### `AnchorWriter`: the destination refuses

The property that matters is not that a write succeeds but that **deletion and overwrite fail** with the
adapter's credential. Test, against the real storage in a test bucket:

1. a write succeeds and the object reads back with the same hashes;
2. deleting the object with the adapter's credential fails;
3. overwriting it with the adapter's credential fails or produces a new version while the original stays;
4. the retention on the object cannot be shortened.

These are integration tests, outside the unit suite.

### Commitments in your row model

Test the erasure contract on a real row: after erasure the plaintext and the salt are gone, the commitment bytes
are unchanged, and the row hash recomputed with your serializer equals the stored one.

Installed on its own, the functional suite is skipped: with `symfony/event-dispatcher` required for development
only, Symfony's container leaves the event dispatcher out and no kernel boots. It runs from an application that
requires an HTTP kernel, such as the monorepo root.
