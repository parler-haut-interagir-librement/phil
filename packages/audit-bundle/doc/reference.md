# API reference

Every type under `Phil\AuditBundle\`, by layer.

## Bundle

| Type | Role | State |
|---|---|---|
| `PhilAuditBundle` | `AbstractBundle`, alias `phil_audit` | Configured and wired ([configuration.md](configuration.md)) |

## Domain

### `Domain\Commitment`

| Type | Role | State |
|---|---|---|
| `Salt` | Per-row, per-field HMAC key; `MIN_BYTES = 16`; `generate()`, `fromBytes()`; public `$bytes` | Implemented and tested |
| `Commitment` | `HMAC-SHA256(key = salt, message = value)`, 32 raw bytes; `of()`, `fromBytes()`, `matches()`, `equals()` in constant time; public `$bytes` | Implemented and tested |

### `Domain\Chain`

| Type | Role | State |
|---|---|---|
| `AuditRow`, `RowKind` | A row: segment, sequence, kind, instant, serialization version, payload, previous hash, hash; `append()`, `restore()`, `isIntact()`; `GENESIS` | Implemented and tested |
| `Anchor` | The boundary hashes of a segment at the end of a period | Implemented and tested |
| `ChainVerifier`, `ChainBreak` | Verifies a segment against its anchors in one pass; what is wrong, an alarm or a break explained by an anchored discontinuity (`isAlarm()`) | Implemented and tested |
| `Facts`, `Subject` | What a row is indexed by and its hash covers: action, actor, subject, tenant; `of()`, `read()`, `none()`, `inTenant()`, `restore()`, `isWellFormed()`, `isRead()` | Implemented and tested |
| `SiteSegment` | The segment of one tenant: `of()`, `isSite()`, `belongsTo()`, `base()`; `DIGEST_LENGTH = 32` (128 bits), `MAX_BASE_LENGTH = 29` | Implemented and tested |

### `Domain\Contract`

| Type | Role | State |
|---|---|---|
| `CanonicalSerializer` | `version()` and `serialize($fields)`: the frozen bytes a row hash is taken over | Port only; no implementation shipped, on purpose |
| `AnchorWriter` | `write($segment, $periodEnd, $firstHash, $headHash)` to append-only storage | Port |
| `AnchorReader` | `anchors($segment)`: the anchors written, oldest first | Port |
| `AuditRowRepository` | Where rows are appended, read in order (`rows()`, `rowsAfter()`), and the two ends of a segment (`first()`, `head()`) read on the key | Port |
| `AuditLogReader` | `page()`, `row()` | Port |
| `FactExporter` | `export($facts)`: a batch of facts to a dashboard | Port |
| `FeedCheckpoints` | `position($feed, $segment)`, `advance($feed, $segment, $sequence)`: where a feed stopped | Port |

### `Domain\Exception` and `Domain\Export`

| Type | Role | State |
|---|---|---|
| `ChainConflict` | A place in a segment taken twice | Exception |
| `ExportedFact` | What a dashboard receives of a row, identifiers only; `of()`, `toArray()`, `$unixNanos` | Implemented and tested |

### `Domain\Read`

| Type | Role | State |
|---|---|---|
| `AuditQuery`, `Order` | The filters, order, page size and cursor of a read, `reads()` for the reads only; `matches()` and `isAfterCursor()` for an adapter that scans | Implemented and tested |
| `Cursor`, `AuditPage` | Where a page stopped, as a token; a page and the cursor of the next | Implemented and tested |
| `SegmentStatus`, `ChainState` | A segment's head and, once verified, its breaks and state; its site (`site()`) and the segment named (`base()`) | Implemented and tested |

## Application

| Type | Role | State |
|---|---|---|
| `AuditTrail` | `record($segment, $fields, $facts)`, `recordRead($segment, $what, $subject, $actor, $fields)`, `recordDiscontinuity($segment, $reason, $tenant)`, `segmentFor($segment, $tenant)`, retried on a race, stamped with the tenant, in the site's segment with site segments, passed to the notifier | Implemented and tested |
| `AuditLog` | `page()`, `history()`, `row()`, `each()`, held to the current tenant, every row held to the query with `===` | Implemented and tested |
| `ChainStatus` | `segments()`, `verify($segment)`, held to the current tenant's site | Implemented and tested |
| `DashboardFeed` | `feed($segments, $max)`, `feedSegment($segment, $max)`, `lag($segment)`: the facts since the feed's place | Implemented and tested |
| `Exception\ExportFailed` | A batch that did not reach its destination | Exception |
| `Contract\TenantScope`, `Contract\ActorResolver`, `Contract\AuditNotifier`, `Contract\PayloadReader` | Ports of the context and of the reading | Ports |
| `Contract\AuditActor`, `Contract\Auditable` | Implemented by the user class and by a workflow's subject | Interfaces |
| `Message\AuditRecorded` | The place of a recorded row, dispatched on a bus | Implemented and tested |
| `Anchoring` | Anchors every segment from its first and head rows, without reading the rows between | Implemented and tested |
| `TrailVerification` | Verifies every segment, or those named, or those of one site | Implemented and tested |

## Infrastructure

| Type | Role | State |
|---|---|---|
| `DbalAuditRowRepository`, `AuditTable`, `AuditSchemaListener` | The tables `phil_audit_row` and `phil_audit_feed` of a Doctrine connection, for its platform (`AuditTable::configure($schema, $platform)`); the tenant, the actor and the record's identifier in a binary collation (`AuditTable::binaryCollation()`) | Implemented and tested |
| `DbalFeedCheckpoints` | The feeds' places in `phil_audit_feed`, only ever moved forward | Implemented and tested |
| `JsonLinesFactExporter`, `OtlpLogExporter` | Facts as JSON Lines; facts as OpenTelemetry log records over OTLP/HTTP | Implemented and tested |
| `JsonLinesAnchorStore` | Anchors appended to a file: development and tests | Implemented and tested |
| `AnchorCommand`, `VerifyCommand`, `FeedCommand` | `phil:audit:anchor`, `phil:audit:verify [--tenant] [--accept-explained]`, `phil:audit:feed [--max]` | Implemented and tested |
| `DbalAuditLogReader`, `ScanningAuditLogReader` | The log read on the table's indexes, every row held to the query with `===`, or by scanning a repository | Implemented and tested |
| `MessengerAuditNotifier`, `TenancyTenantScope`, `SecurityActorResolver`, `WorkflowAuditListener` | The adapters of the context | Implemented and tested |
| `FeedOnRecorded` | Feeds the segment of each `AuditRecorded` message | Implemented and tested |
| `ContextPass` | Removes an adapter of the context whose service is missing | Implemented and tested |

## Errors

`ChainConflict` (a `DomainError`) for a place in a segment taken twice; `ExportFailed` (a `RuntimeException`) for a
batch of facts that did not land. `Salt::fromBytes()`, `Commitment::fromBytes()`, `AuditRow::append()`, `Facts`,
`Subject`, `SiteSegment::of()`, `AuditQuery` and `Cursor::fromToken()` throw
`Phil\DddBundle\Domain\Exception\InvalidValue` from `phil/ddd-bundle`.

## Not started

The erasure operation: the application deletes a field's plaintext and salt and keeps the commitment. See
[hash-chain.md](hash-chain.md).
