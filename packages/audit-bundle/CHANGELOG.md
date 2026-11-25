# Changelog

All notable changes to `phil/audit-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Added

- Reads of sensitive data: `Facts::read($what, $subject, $actor, $tenant)` builds the facts of a read, the action
  `read.` then what was read, always naming the record read; `Facts::READ_PREFIX` and `Facts::isRead()`;
  `AuditTrail::recordRead()` records one; `AuditQuery::reads()` keeps the reads only. The
  [reading guide](doc/reading.md#reads-of-sensitive-data) says when to record a read and what volume to expect.
- The [ASVS 5.0 mapping](doc/asvs.md).
- Tests of a clock in another time zone, through storage and verification, and of the end of daylight saving time;
  of facts holding a carriage return, a NUL byte, a terminal escape or another control character, refused when written
  and not intact when stored.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `symfony/http-client-contracts` 3.8).

## 0.1.0-alpha - 2026-11-14

### Added

- The hash chain: `AuditRow`, `RowKind`, `Anchor`, `Domain\Contract\AuditRowRepository` (with `first($segment)` and
  `rowsAfter($segment, $sequence, $limit)`), `Domain\Exception\ChainConflict`, `ChainVerifier`, `ChainBreak`; the facts
  each row is indexed by and its hash covers, `Facts` and `Subject` (action, actor, subject, tenant: identifiers, an
  actor never an e-mail address), and a row whose segment, version or facts hold a line break is not intact; the ports
  `Domain\Contract\CanonicalSerializer` (port only; no implementation shipped, on purpose (a one-way door)),
  `Domain\Contract\AnchorWriter` (port only) and `Domain\Contract\AnchorReader`. Verification hashes the payload as
  stored. `ChainVerifier` reads the rows once and keeps only the anchored heads it finds, the first hash and the
  discontinuity rows: memory does not grow with the segment, and each anchor is a lookup. The anchors that hold are
  sorted by period once, the next one after a lost anchor is found by a binary search, and the latest head found answers
  for every discontinuity. A lost anchored head is accepted only when a discontinuity row recorded after that anchor's
  period, and no later than the next anchor that still holds, is covered by that anchor; once a segment has anchors, a
  discontinuity row no anchor covers is a break. A break a discontinuity explains is still returned, marked `explained`
  (`ChainBreak::isAlarm()`).
- One chain per site: `SiteSegment`, the segment of one tenant (the segment named, `.t-` and 32 hexadecimal digits,
  128 bits, of the SHA-256 of the tenant, with test vectors; a segment name of at most 29 characters); with
  `site_segments`, `AuditTrail` records each tenant's rows, and a site's discontinuity, in its own segment;
  `ChainVerifier` refuses in a site segment a row of another tenant, or of none, taking each tenant's digest once per
  segment; `ChainStatus` shows a tenant its own segments only; `TrailVerification` and `phil:audit:verify --tenant`
  verify one site; `SegmentStatus::site()` and `base()`.
- The feed to dashboards: `DashboardFeed`, which sends the facts recorded since its place in each segment, read on
  the key a batch at a time, at least once, bounded per run, with `lag()`; `ExportedFact`, identifiers only and never
  a payload; the ports `FactExporter` and `FeedCheckpoints`; `JsonLinesFactExporter`, `OtlpLogExporter` (OpenTelemetry
  logs over OTLP/HTTP and JSON, redirects never followed, the endpoint read by `phil/url` as the container compiles),
  `DbalFeedCheckpoints` and the table `phil_audit_feed`, whose places only move forward; `ExportFailed`;
  `phil:audit:feed`, and `FeedOnRecorded`, which feeds after each `AuditRecorded` message.
- `Domain\Commitment\Salt`, `Domain\Commitment\Commitment`.
- `AuditTrail`, which stamps a row with the current tenant and tells the `AuditNotifier` once it is recorded,
  `Anchoring`, which reads the first and head rows of each segment, and `TrailVerification`.
- The read side: `AuditQuery` (actor, action or action prefix, subject or subject type, segment, tenant, half-open
  period, order, page size), `Cursor`, `AuditPage`, the port `AuditLogReader`; `AuditLog` (`page()`, `history()`,
  `row()`, `each()`), held to the current tenant, which holds every row a reader answers to the query with `===`
  (`AuditQuery::matches()`); `ChainStatus`, `SegmentStatus` and `ChainState` for a viewer.
- The ports `TenantScope`, `ActorResolver`, `AuditNotifier` and `PayloadReader`, the interfaces `AuditActor` and
  `Auditable`, and the message `AuditRecorded`, which carries a row's segment and sequence only.
- `DbalAuditRowRepository`, its table — the facts in columns of their own, an index for each way of reading; the
  tenant, the actor and the record's identifier in the binary collation of MySQL and MariaDB (`utf8mb4_bin`) or the
  `C` collation of PostgreSQL, so that one tenant never reads another's rows — and schema listener
  (`AuditTable::configure($schema, $platform)`, `AuditTable::binaryCollation()`); `DbalAuditLogReader`, which pages on
  the key and keeps only the rows that pass the query with `===`, and `ScanningAuditLogReader`;
  `JsonLinesAnchorStore`.
- `WorkflowAuditListener`, `MessengerAuditNotifier`, `TenancyTenantScope` and `SecurityActorResolver`, each
  registered when its component is, and `ContextPass`, which removes an adapter whose service is missing.
- The keys `connection`, `serializer`, `anchor_writer`, `anchor_reader`, `anchor_file`, `dispatch_bus`,
  `workflow_segment`, `site_segments` and `feed` (`name`, `batch`, `exporter`, `json_lines`, `otlp`, `checkpoints`). The
  clock is required: `AuditTrail` and `AnchorCommand` take a `ClockInterface` before their optional arguments, and the
  bundle wires the container's `clock` service, without falling back to the system's time.
- `phil:audit:anchor` and `phil:audit:verify`, which prints the breaks a discontinuity explains, marked `explained`;
  such a segment reads `holds, with N explained break(s) to review`, and the command fails on them as on alarms
  until `--accept-explained` is given, once they were reviewed; an alarm fails it with or without the option.
- The benchmarks of the verifier, the reader and the feed, and the fuzz targets `cursor-token` and `site-segment`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
