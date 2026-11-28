# Reference

Every public type of the package, with its role. Names are relative to `Phil\CalendarImport`. Types marked
`@internal` in the code (`ICalendar\ContentLines`, `ContentLine`, `ComponentTree`, `Component`, `EventReader`,
`TimeZones`, `Text`, `Exception\EventRefused`) are not part of the API.

## Reading iCalendar

| Type | Kind | Role |
|---|---|---|
| `ICalendar\ICalendarReader` | `final readonly class` | `read(string $text, ?DateTimeZone $zone = null): ReadResult`; throws `UnreadableCalendar`. |
| `ICalendar\ReaderLimits` | `final readonly class` | The bounds: `bytes`, `events`, `lineBytes`, `depth`, `components`, with their `MAX_*` constants. |
| `ICalendar\Limit` | `enum` | The bound a text passed: `Bytes`, `Events`, `LineBytes`, `Depth`, `Components`; `describe()`. |
| `ICalendar\ReadResult` | `final readonly class` | `events` (list of `SourceEvent`), `rejected` (list of `RejectedEvent`), `name`. |
| `ICalendar\RejectedEvent` | `final readonly class` | An event refused alone: `uid` and `key` when known, `reason`, `message`. |
| `ICalendar\Rejection` | `enum` | Why an event was refused; see [Reading iCalendar](reading.md#one-event). |
| `SourceEvent` | `final readonly class` | An event as the source gives it: UID, occurrence, sequence, times, texts, recurrence; `key()`, `keyOf()`, `version()`. |
| `EventStatus` | `enum` | `Tentative`, `Confirmed`, `Cancelled`. |

## Sources

| Type | Kind | Role |
|---|---|---|
| `Source\EventSource` | interface | The port of a source: `fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched`. |
| `Source\IcsUrlSource` | `final readonly class` | An iCalendar feed read by its address through `phil/safe-http`; `create()`. |
| `Source\CalendarSource` | `final readonly class` | A calendar to import: `id`, `url`, `zone`; `of()`. |
| `Source\FetchValidators` | `final readonly class` | `etag` and `lastModified` of the last answer; `headers()`, `isEmpty()`. |
| `Source\Fetched` | `final readonly class` | A calendar read (`result`) or nothing changed (`isNotModified()`), with the `validators` of the answer. |
| `Source\FailureKind` | `enum` | `Unreachable`, `Status`, `Refused`, `TooLarge`, `Invalid`. |

## Snapshots and state

| Type | Kind | Role |
|---|---|---|
| `EventVersion` | `final readonly class` | `sequence`, `lastModified`, `fingerprint`, `fields`; `changedFrom()`, `changedFields()`, `toArray()`, `fromArray()`. |
| `State\Snapshot` | `final readonly class` | `takenAt` and the `versions` by key; `compare()`, `toArray()`, `fromArray()`. |
| `State\SnapshotDiff` | `final readonly class` | `added`, `changed`, `removed`; `reason()`, `fieldsOf()`, `isEmpty()`. |
| `State\ChangeReason` | `enum` | `Sequence`, `LastModified`, `Content`. |
| `State\SourceState` | `final readonly class` | The state of a source between reads; `succeeded()`, `notModified()`, `failed()`, `isFailing()`. |
| `State\SourceFailure` | `final readonly class` | `kind`, `message`, `at`, `status`. |
| `State\SourceStateStore` | interface | `load(string $sourceId): ?SourceState`, `save(SourceState $state): void`. |
| `State\RefreshPolicy` | `final readonly class` | `interval`, `retry`, `backoff`; `nextAt()`, `isDue()`. |

## Importing

| Type | Kind | Role |
|---|---|---|
| `CalendarImporter` | `final readonly class` | `refresh(CalendarSource $source): ImportOutcome`, `refreshDue(iterable $sources): list<ImportOutcome>`. |
| `ImportOutcome` | `final readonly class` | `read`, `state`, `changes`, `drafts`, `rejected`; `isFailure()`, `changesOf()`. |
| `Change` | `final readonly class` | `kind`, `key`, `draft`, `reason`, `conflicts`. |
| `ChangeKind` | `enum` | `Added`, `Changed`, `Removed`. |
| `ImportedDraft` | `final readonly class` | A neutral draft, marked with its source, key and UID; `of()`, `isCancelled()`. |
| `Field` | `enum` | The fields an editor may replace: `Title`, `Location`, `Description`, `Tags`. |
| `Override\EditorOverride` | `final readonly class` | Fields replaced and `hidden`, by key; `replaces()`, `fields()`. |
| `Override\OverrideStore` | interface | `forSource(string $sourceId): iterable<EditorOverride>`. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\CalendarImportException` | Interface of every exception of the library. |
| `Exception\UnreadableCalendar` | Interface of a text refused whole: `InvalidCalendar` or `LimitExceeded`. |
| `Exception\InvalidCalendar` | Not iCalendar, not UTF-8, or built wrongly. |
| `Exception\LimitExceeded` | A bound passed; `limit` names it, `max` gives it. |
| `Exception\SourceUnavailable` | A source that gave no calendar; `kind`, `status`. |
| `Exception\InvalidSource` | An identifier, address, zone, override or stored data that does not hold together. |
| `Exception\InvalidLimits` | A bound or a delay outside its range. |
