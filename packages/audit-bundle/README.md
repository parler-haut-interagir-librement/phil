# phil/audit-bundle

**An append-only audit trail that shows tampering and still lets you erase personal information.**

phil/audit-bundle keeps an append-only, hash-chained audit trail in a table of a Doctrine connection. Each row is
hashed with SHA-256 over its fields and the hash of the row before, per segment. A discontinuity row records a
restore. The boundary hashes of every segment are anchored through an `AnchorWriter`, and a command verifies each
segment against its anchors and fails loudly on a break. The trail holds up against an administrator with access to
the database only when the anchors go to append-only storage that administrator cannot rewrite. Without anchors, a
full rewrite of the chain goes undetected.

It exists because a tamper-evident log and the right to erasure pull against each other: hashing a plaintext value
into the chain makes it impossible to delete. Here a personal field enters the chain only as a per-row salted
commitment, `HMAC-SHA256(key = salt, message = value)`, with a salt of at least 128 bits from a CSPRNG and a
constant-time comparison. To erase the field, the application deletes the plaintext and the salt and keeps the
commitment, so every hash and anchor still verifies. The bundle does not perform that erasure itself.

Each row is indexed by its facts — who acted, what was done, to which record, in which tenant —, identifiers the
hash covers, so that the log is read back by actor, action, record and period, a page at a time on its indexes. The
history of a record holds the changes of state its Symfony workflows went through beside every other event about it.
The bundle exposes the state of the chain for a viewer, holds rows and reads to the current tenant when
`phil/tenancy-bundle` is installed, and can give each site — each tenant — a chain of its own, anchored and verified
on its own. It feeds dashboards with the facts of each row, identifiers only and never a payload, as JSON Lines, as
OpenTelemetry log records or through an exporter of yours, at least once and from where the feed stopped. These are
services without screens: `phil/audit-viewer` shows them in an EasyAdmin back office.

It is for Symfony applications that must show who did what to records holding personal information, and it pairs
well with `phil/privacy-bundle`, which declares personal fields and assembles access and erasure answers. It is a
log, not event sourcing, and it is read only: going back to an earlier version of a record belongs to the bundle that
keeps real versions of it. By design it does not ship a canonical serialization before the application freezes one,
store anchors, partition tables, define database roles, or decide which events are audited and which fields are
personal.

The bundle builds on `phil/ddd-bundle` and PSR-20 clocks, with Doctrine DBAL, the Console, HttpClient, Messenger,
Security and Workflow components and `phil/tenancy-bundle` as options. It is built of ports and adapters: it carries
what the design decides, and leaves ports such as `CanonicalSerializer`, `AnchorWriter`, `AnchorReader` and
`AuditRowRepository` for what the application decides.

## Status

| Type | State |
|---|---|
| `Domain\Commitment\Salt`, `Domain\Commitment\Commitment` | Implemented and tested |
| `Domain\Contract\CanonicalSerializer` | Port only; no implementation shipped, on purpose (a one-way door) |
| `Domain\Contract\AnchorWriter`, `AnchorReader` | Ports; `JsonLinesAnchorStore` for development |
| `AuditRow`, `RowKind`, `ChainVerifier`, `ChainBreak`, `Anchor`, `Domain\Contract\AuditRowRepository` | Implemented and tested |
| `SiteSegment`; `site_segments`, `phil:audit:verify --tenant` | Implemented and tested |
| `DashboardFeed`, `ExportedFact`, `FactExporter`, `FeedCheckpoints`; `JsonLinesFactExporter`, `OtlpLogExporter`, `DbalFeedCheckpoints`, `phil:audit:feed`, `FeedOnRecorded` | Implemented and tested |
| `AuditTrail` (events, discontinuities), `Anchoring`, `TrailVerification` | Implemented and tested |
| `DbalAuditRowRepository` and its table; `phil:audit:anchor`, `phil:audit:verify` | Implemented and tested |
| `Facts`, `Subject`; `AuditQuery`, `Cursor`, `AuditPage`; `AuditLog`, `ChainStatus` | Implemented and tested |
| `DbalAuditLogReader`, `ScanningAuditLogReader` | Implemented and tested |
| Tenant, actor and record compared byte by byte (binary collation, then `===` in PHP) | Implemented and tested |
| `WorkflowAuditListener`, `MessengerAuditNotifier`, `TenancyTenantScope`, `SecurityActorResolver` | Implemented and tested; registered when their component is |
| `PayloadReader` | Port only; the inverse of the application's serializer |
| Erasure of a field | The application's: the plaintext and the salt deleted, the commitment kept |
| `PhilAuditBundle` | Configuration `connection`, `serializer`, `anchor_writer`, `anchor_reader`, `anchor_file`, `dispatch_bus`, `workflow_segment`, `site_segments`, `feed` |

## Documentation

- [Installation](doc/installation.md) — requirements, including why `phil/ddd-bundle` and `symfony/config` are
  declared, and registering the bundle.
- [Configuration](doc/configuration.md) — the keys under `phil_audit` (`connection`, `serializer`, `anchor_writer`,
  `anchor_reader`, `anchor_file`, `dispatch_bus`, `workflow_segment`, `site_segments`, `feed`), the services wired,
  and how to register your own adapters.
- [Reading the log](doc/reading.md) — the facts a row is indexed by, `AuditQuery`, pages and cursors, `AuditLog`,
  the history of a record, `ChainStatus`, and the two readers.
- [Integrations](doc/integrations.md) — changes of state from Symfony Workflow, the current account from the
  Security component, the tenant from `phil/tenancy-bundle`, and the dispatch to dashboards with Messenger.
- [One chain per site](doc/sites.md) — site segments, what verification adds, what a tenant sees, and
  `SiteSegment`.
- [Feeding dashboards](doc/dashboards.md) — the feed, its places, at least once and bounded, the JSON Lines and
  OpenTelemetry exporters, and an exporter of your own.
- [The hash chain](doc/hash-chain.md) — the threat model and the full design (chained rows, versioned
  serialization, erasure that keeps the hash, discontinuity rows, per-segment verification), with what exists of
  each.
- [Salted commitments and erasure](doc/commitments-and-erasure.md) — why HMAC, `Salt` and `Commitment` method by
  method, storage, and the erasure contract.
- [Anchoring](doc/anchoring.md) — why anchors decide whether the trail holds, acceptable destinations, and the
  `AnchorWriter` port.
- [The serialization door](doc/serialization-door.md) — why the bundle ships no serialization, versioning, the
  decisions an implementation must freeze, and an example.
- [Extension points](doc/extension-points.md) — every port method by method, adapter invariants, a serializer
  registry, an object-lock anchor writer and a payload reader.
- [ASVS 5.0 mapping](doc/asvs.md) — the requirements of chapter V16 the bundle meets, the tests that show them, and
  those left to the application.
- [Testing](doc/testing.md) — how the bundle is tested, its benchmarks and fuzz targets, running its tests in a
  monorepo or alone, golden vectors for a serializer and refusal tests for an anchor destination.
- [API reference](doc/reference.md) — every type by layer, with its role and state.

## Quick start

```bash
composer require phil/audit-bundle:^0.1@dev
```

```php
use Phil\AuditBundle\Domain\Commitment\Commitment;
use Phil\AuditBundle\Domain\Commitment\Salt;

$salt = Salt::generate();                      // 16 bytes from the CSPRNG, stored beside the plaintext
$commitment = Commitment::of($salt, $email);   // 32 bytes, kept forever, hashed into the row

// Erasure: delete the plaintext and the salt, keep the commitment. The row hash does not move.
Commitment::fromBytes($stored)->matches(Salt::fromBytes($storedSalt), $email); // constant time
```

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditQuery;

$trail->record('orders', ['amount' => '10.00'], Facts::of('order.paid', $account->id(), Subject::of('order', '42')));

$page = $log->page(AuditQuery::all()->byActor($account->id())->limitedTo(100));   // the latest first
$history = $log->history(Subject::of('order', '42'));                              // the earliest first
```

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    site_segments: true                                   # one chain per tenant
    feed:
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl' # facts for the dashboards, identifiers only
```

Before anchoring anything, write and freeze your `CanonicalSerializer`, and point your `AnchorWriter` at
append-only storage.

## Requirements

PHP 8.5 or later, `phil/ddd-bundle` 0.1, `psr/clock` 1.0, `symfony/config` and `symfony/dependency-injection` 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
