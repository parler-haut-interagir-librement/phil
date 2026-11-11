# Testing

## The library's tests

The suite `tests/Unit` tests the identifiers, codes and fingerprints and their bounds; credentials and the
checkpoints they open; every rule of the policy at its boundary second; attendance and its transitions; the
invariants of a check-in; the desk — unknown codes, admissions, refusals, check-outs and re-entries, replays, races
decided again and the contention it gives up on —; the in-memory ledger; manifests, their chunks, labels and limit;
the reading of journals and every malformed scan; and the synchronisation, its order, conflicts, duplicates, bounds
and races.

From the monorepo root:

```sh
vendor/bin/phpunit packages/checkin/tests
```

The library is held to 100 % covered mutation score by Infection.

## The fuzz target

`tests/Fuzz/offline-batch.php` gives `OfflineBatch::read()` JSON nobody wrote, from the seeds of
`tests/Fuzz/corpus/offline-batch`: whatever it reads, it returns scans or throws `InvalidOfflineBatch`.
`composer fuzz`, from the monorepo root, runs it.

## The benchmark

`tests/Benchmark/CheckInDeskBench.php` measures a scan decided and recorded in memory, the decision alone, a
fingerprint, a manifest of 10 000 credentials and a journal of 500 scans. `composer bench`, from the monorepo root,
runs it with the other benchmarks.

## Testing a check-in

Build the desk on the in-memory ledger and a frozen clock, such as Symfony's `MockClock`, with a resolver of a few
credentials:

```php
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanOutcome;
use Symfony\Component\Clock\MockClock;

$ledger = new InMemoryCheckInLedger();
$desk = new CheckInDesk($resolver, $ledger, new MockClock('2026-11-08 10:00:00'));

$desk->scan(CredentialCode::of('T-1001'), CheckpointId::of('hall'), DeviceId::of('door-1'));
$again = $desk->scan(CredentialCode::of('T-1001'), CheckpointId::of('hall'), DeviceId::of('door-2'));

self::assertSame(ScanOutcome::AlreadyAdmitted, $again->outcome);
self::assertCount(2, $ledger->all());
```
