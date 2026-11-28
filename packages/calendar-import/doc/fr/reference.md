<!-- phil-dev source-sha256=799006ef48f1744997ab587d1a019a40faf2b63fa2c5a2e987616c5e8a3f16c5 -->
# Référence

Chaque type public du paquet, avec son rôle. Les noms sont relatifs à `Phil\CalendarImport`. Les types marqués
`@internal` dans le code (`ICalendar\ContentLines`, `ContentLine`, `ComponentTree`, `Component`, `EventReader`,
`TimeZones`, `Text`, `Exception\EventRefused`) ne font pas partie de l'API.

## Lire de l'iCalendar

| Type | Genre | Rôle |
|---|---|---|
| `ICalendar\ICalendarReader` | `final readonly class` | `read(string $text, ?DateTimeZone $zone = null): ReadResult` ; lance `UnreadableCalendar`. |
| `ICalendar\ReaderLimits` | `final readonly class` | Les bornes : `bytes`, `events`, `lineBytes`, `depth`, `components`, avec leurs constantes `MAX_*`. |
| `ICalendar\Limit` | `enum` | La borne qu'un texte a dépassée : `Bytes`, `Events`, `LineBytes`, `Depth`, `Components` ; `describe()`. |
| `ICalendar\ReadResult` | `final readonly class` | `events` (liste de `SourceEvent`), `rejected` (liste de `RejectedEvent`), `name`. |
| `ICalendar\RejectedEvent` | `final readonly class` | Un événement refusé seul : `uid` et `key` quand ils sont connus, `reason`, `message`. |
| `ICalendar\Rejection` | `enum` | Pourquoi un événement a été refusé ; voir [Lire de l'iCalendar](reading.md#un-seul-événement). |
| `SourceEvent` | `final readonly class` | Un événement tel que la source le donne : UID, occurrence, séquence, heures, textes, récurrence ; `key()`, `keyOf()`, `version()`. |
| `EventStatus` | `enum` | `Tentative`, `Confirmed`, `Cancelled`. |

## Sources

| Type | Genre | Rôle |
|---|---|---|
| `Source\EventSource` | interface | Le port d'une source : `fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched`. |
| `Source\IcsUrlSource` | `final readonly class` | Un flux iCalendar lu par son adresse, par `phil/safe-http` ; `create()`. |
| `Source\CalendarSource` | `final readonly class` | Un calendrier à importer : `id`, `url`, `zone` ; `of()`. |
| `Source\FetchValidators` | `final readonly class` | `etag` et `lastModified` de la dernière réponse ; `headers()`, `isEmpty()`. |
| `Source\Fetched` | `final readonly class` | Un calendrier lu (`result`) ou rien n'a changé (`isNotModified()`), avec les `validators` de la réponse. |
| `Source\FailureKind` | `enum` | `Unreachable`, `Status`, `Refused`, `TooLarge`, `Invalid`. |

## Instantanés et état

| Type | Genre | Rôle |
|---|---|---|
| `EventVersion` | `final readonly class` | `sequence`, `lastModified`, `fingerprint`, `fields` ; `changedFrom()`, `changedFields()`, `toArray()`, `fromArray()`. |
| `State\Snapshot` | `final readonly class` | `takenAt` et les `versions` par clé ; `compare()`, `toArray()`, `fromArray()`. |
| `State\SnapshotDiff` | `final readonly class` | `added`, `changed`, `removed` ; `reason()`, `fieldsOf()`, `isEmpty()`. |
| `State\ChangeReason` | `enum` | `Sequence`, `LastModified`, `Content`. |
| `State\SourceState` | `final readonly class` | L'état d'une source entre deux lectures ; `succeeded()`, `notModified()`, `failed()`, `isFailing()`. |
| `State\SourceFailure` | `final readonly class` | `kind`, `message`, `at`, `status`. |
| `State\SourceStateStore` | interface | `load(string $sourceId): ?SourceState`, `save(SourceState $state): void`. |
| `State\RefreshPolicy` | `final readonly class` | `interval`, `retry`, `backoff` ; `nextAt()`, `isDue()`. |

## Importer

| Type | Genre | Rôle |
|---|---|---|
| `CalendarImporter` | `final readonly class` | `refresh(CalendarSource $source): ImportOutcome`, `refreshDue(iterable $sources): list<ImportOutcome>`. |
| `ImportOutcome` | `final readonly class` | `read`, `state`, `changes`, `drafts`, `rejected` ; `isFailure()`, `changesOf()`. |
| `Change` | `final readonly class` | `kind`, `key`, `draft`, `reason`, `conflicts`. |
| `ChangeKind` | `enum` | `Added`, `Changed`, `Removed`. |
| `ImportedDraft` | `final readonly class` | Un brouillon neutre, marqué de sa source, de sa clé et de son UID ; `of()`, `isCancelled()`. |
| `Field` | `enum` | Les champs qu'un éditeur peut remplacer : `Title`, `Location`, `Description`, `Tags`. |
| `Override\EditorOverride` | `final readonly class` | Les champs remplacés et `hidden`, par clé ; `replaces()`, `fields()`. |
| `Override\OverrideStore` | interface | `forSource(string $sourceId): iterable<EditorOverride>`. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\CalendarImportException` | Interface de chaque exception de la bibliothèque. |
| `Exception\UnreadableCalendar` | Interface d'un texte refusé en entier : `InvalidCalendar` ou `LimitExceeded`. |
| `Exception\InvalidCalendar` | Pas de l'iCalendar, pas de l'UTF-8, ou mal construit. |
| `Exception\LimitExceeded` | Une borne dépassée ; `limit` la nomme, `max` la donne. |
| `Exception\SourceUnavailable` | Une source qui n'a donné aucun calendrier ; `kind`, `status`. |
| `Exception\InvalidSource` | Un identifiant, une adresse, un fuseau, une surcharge ou des données stockées qui ne tiennent pas ensemble. |
| `Exception\InvalidLimits` | Une borne ou un délai hors de sa plage. |
