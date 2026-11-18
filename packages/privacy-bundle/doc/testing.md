# Testing

## How the bundle is tested

The unit tests live under `tests/Unit/`, namespace `Phil\PrivacyBundle\Tests\`, written for PHPUnit 13
with `#[CoversClass]` on every test class. None boots a kernel; only the database tests touch files, in a
directory of their own under the system's temporary directory, removed after each test:

| Area | Tests |
|---|---|
| `Domain\Attribute`, `Domain\Declaration` | the attribute's validation; parent private declarations read first; coverage with and without a schedule (fixtures `BaseModel`, `Member`) |
| `Domain\Subject` | identifier, category and module name validation; erasure report invariants |
| `Domain\Redaction` | denylist normalisation and patterns; query strings, user information, token paths; long and short options; context walking, throwables; the placeholder on a failing pattern |
| `Domain\Retention` | cutoffs, month clamping, leap days, UTC; rule contradictions; schedule orders and jobs without cutoff; what a rule declares for the register, and its limits |
| `Domain\Cookie` | the inventory read from its map, every refusal, families of names, groups by category |
| `Domain\GuestRequest`, `Domain\Incident` | a secret of which only the hash is kept, the expiry; the notifications a serious risk asks for, every date refused before the discovery |
| `Application` | module ordering, failure isolation and misattributed reports in `SubjectErasure`; sorted sections in `SubjectExport`; purger checks before purging in `RetentionRun`; the activities and the gaps of the register (fixtures `FixedExporter`, `RecordingEraser`, `RecordingPurger`, `FixedScheduleSource`) |
| `Application\Lock` and the work in steps | `HeldLock` on a store whose leases expire on a mock clock (fixture `ExpiringLockStore`): renewed between steps, a work longer than the lifetime keeps its lock, a lease expired or taken is lost, a failing store too; each export, erasure, account deletion, download and retention run whose lock is taken during a step (fixture `InterruptingModule`) stops before the next one and keeps what it saved |
| `Application\Export` | the archive opened by the zip extension (fixture `Unzip`), its headers byte by byte, deflated and stored files, every unsafe name refused, the README |
| `Application\GuestRequest` | the link sent and opened once, the access and the erasure, an incomplete erasure kept, the expiry, the lock, both limits, nothing about a person in the limiter's storage (fixtures `MemoryGuestRequests`, `RecordingLinkSender`) |
| `Infrastructure\Monolog` | message, context and `extra` redacted on a real `LogRecord` |
| `Domain\Anonymization` | every built-in anonymizer on a seeded random source and a fixed salt, so each value is known; the IBAN check digits of every country shape; the shifts of a date; the locale packs, their data fingerprinted and their telephone patterns checked against the fictional ranges; options, rules, plans and declarations through parents (fixture `Customer`) |
| `Application\Anonymization` | the run on tables in memory (fixture `MemoryTarget`): one transaction, nulls left, a seed repeated, a salt drawn per run, every refusal before a row is read, the rollback on a bad option, keys of several columns; the guard |
| `Infrastructure\Database` | the identity of each platform's connection, without connecting; the DBAL target in pages, by key, on quoted names; the requests without an account, their address sealed, and the register of incidents on SQLite in memory; the keys in `utf8mb4_bin` on MySQL and MariaDB only |
| `Infrastructure\Doctrine` | the plan drawn from an ORM mapping on SQLite in memory: attributes, categories, configuration, single table inheritance, a mapped superclass, an embeddable |
| `Infrastructure\Console` | the anonymize command on a SQLite file: the dry run, the guard, `--confirm`, `--seed`, `--strict`; the register and its gaps; recording, completing and listing incidents |
| `Infrastructure\Twig`, `Infrastructure\Mailer`, `Infrastructure\Http` | what a blocked frame's placeholder receives and every refused option; every form of a YouTube and a Vimeo address turned into its player by `phil/url`, the addresses kept as given and those refused; the link sent at once; the undeclared cookies of a response and the warning |
| `PhilPrivacyBundle` | the extension loaded on an uncompiled `ContainerBuilder`: the tag, the factory, the arguments, every build-time error; a compiled container with an application's anonymizer and locale pack, and without a connection |

### Functional tests

`tests/Functional/` boots a real kernel (`App\TestKernel`: FrameworkBundle, MonologBundle with an in-memory `test`
handler, and the bundle) and checks that what reaches the handler is redacted: a record of the application, a
throwable in its context, the URI the router logs for `/p/{token}?…`, and a 404 message. Other kernels prove that
each kind of malformed configuration, or an unknown key, stops the kernel. Run it with
`vendor/bin/phpunit packages/privacy-bundle/tests/Functional`; installed on its own, where FrameworkBundle is only
a development dependency and Symfony wires no event dispatcher, it is skipped.

`App\RightsKernel` adds a firewall, Twig, Messenger and SQLite in memory, for the rights of a member end to end: the
banner, a decision with and without JavaScript, the Global Privacy Control, the sudo mode on the erasure page, the
cancellation and the export as an archive. `CookiesEmbedsAndGuestsTest` boots it with the cookie inventory of
`rights/config/privacy/cookies.yaml`: the cookie policy, the banner, a frame blocked then granted, the test helper on a
declared and an undeclared cookie, and the requests without an account from the form to the archive and the erasure,
with their guards. The Stimulus controllers run under Node's test runner:
`node --import ./tests/js/register.mjs --test tests/js`.

### From a monorepo root that installs the bundle by path

```bash
vendor/bin/phpunit packages/privacy-bundle/tests
```

The tests run on the root `vendor/` and the root PHPUnit configuration.

### Installed on its own

```bash
cd privacy-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

The package ships no `phpunit.xml`; `--no-configuration` runs the tests with PHPUnit's defaults and the
package's own autoloader (`autoload-dev` maps `Phil\PrivacyBundle\Tests\` to `tests/`).

The package also ships `composer-dependency-analyser.php`: run
[`shipmonk/composer-dependency-analyser`](https://github.com/shipmonk-rnd/composer-dependency-analyser) in the
installed package to check that every imported package is declared and every declared package is imported, with
no exception.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the log redaction path: the Monolog processor on records of
three sizes, the denylist lookup, and the command line redactor on lines up to 20 KB; and the reader of the consent
cookie, which every request runs. Run them from the monorepo root:

```bash
composer bench -- packages/privacy-bundle/tests/Benchmark
```

The database tests need the `pdo_sqlite` extension; the identities of MySQL, MariaDB and PostgreSQL connections are
read without a server.

## Testing your adapters

### Exporters and erasers

Test each adapter against its real storage, and assert the port's invariants:

```php
public function testErasureIsIdempotent(): void
{
    $eraser = new BillingEraser($this->connection);
    $subject = DataSubject::fromString('customer-1');

    $first = $eraser->erase($subject);
    $second = $eraser->erase($subject);

    self::assertSame('billing', $first->module->value);
    self::assertEquals($first->effects, $second->effects);
}
```

Then build `SubjectExport` and `SubjectErasure` from the container once, so a duplicate module name fails a
test rather than a request.

### Purgers and the schedule

- Load your real schedule through your `RetentionScheduleSource` in a test: construction errors surface there.
- Assert that `RetentionRun` built from the container runs on a fixed instant without `MisconfiguredPrivacy`,
  which proves every job line has a purger.
- Assert `jobsWithoutCutoff()` is the list you expect, so a new blank is a test change, not a surprise.
- For a purger, insert rows on both sides of a cutoff and assert only the older ones go.

### Declarations

Write the inventory test shown in [personal-data-declaration.md](personal-data-declaration.md), and run
`bin/console phil:privacy:register --strict` in the continuous integration, so that a store without a purpose or a
location fails it.

### Cookies

Use `AssertsDeclaredCookies` in the functional tests of your pages
([cookie-inventory.md](cookie-inventory.md#a-test-that-fails-on-an-undeclared-cookie)): a cookie added without its line
in the inventory fails the test that visits it.

### Log redaction

Assert your configuration as well as the bundle: push a record through the processor the container built.

```php
final class LogRedactionTest extends KernelTestCase
{
    public function testTheConfiguredDenylistRedactsOurKeys(): void
    {
        $processor = self::getContainer()->get(RedactingProcessor::class); // public in the test container

        $record = $processor(new LogRecord(new DateTimeImmutable(), 'app', Level::Info, 'GET /p/abc?x=1', ['refresh_token' => 't']));

        self::assertSame('GET /p/[redacted]?[redacted]', $record->message);
        self::assertSame(['refresh_token' => '[redacted]'], $record->context);
    }
}
```

The test container exposes private services that are referenced somewhere; if yours removes the processor as
unused, build one with `RedactingProcessor::fromConfiguration()` from the same lists instead.

For short options, a useful guard is a test that lists your console commands' shortcuts and fails while one of
them is missing from `short_options`.

### Anonymizers and the plan

Give an anonymizer a context on a seeded random source and a fixed salt: the same call gives the same value on
every run, which a test can then pin.

```php
$context = new AnonymizationContext(
    new AnonymizerOptions(['prefix' => 'CA']),
    LocalePacks::builtIn()->get('fr_CA'),
    new Randomizer(new Xoshiro256StarStar(hash('sha256', '1', true))),
    'salt',
);

self::assertMatchesRegularExpression('/^CA\d{9}$/', new VatNumberAnonymizer()->anonymize('CA123456789', $context));
```

To check that no personal field is left in clear, run `bin/console phil:privacy:anonymize --dry-run --strict` in
the continuous integration of the application, or assert `AnonymizationPlanReader::plan()->uncovered` is empty.
