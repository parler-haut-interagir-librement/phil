# Extension points

The bundle ships ports of two kinds. Under `Phil\AuditBundle\Domain\Contract`, the two the application always
writes, `CanonicalSerializer` and `AnchorWriter`, with `AnchorReader` and `AuditRowRepository` beside them. Under
`Domain\Contract` too and under `Application\Contract`, the ports of the read side, of the feed and of the context,
each with an adapter the bundle registers when it can, which the application replaces by aliasing the port to its own
service.

## `CanonicalSerializer`

The bytes a row's hash is taken over. Background and the decisions to freeze:
[serialization-door.md](serialization-door.md).

| Method | Contract |
|---|---|
| `version(): non-empty-string` | The identifier written into each row hashed under this serializer, covered by the row hash, and read back by whoever decodes the payload. Stable for the life of the serializer: changing it is a new version. |
| `serialize(array<string, scalar\|null> $fields): string` | Deterministic bytes for the non-personal fields and the commitments, by name. Same fields, same bytes, always. |

Invariants an adapter keeps:

- **deterministic**: independent of array insertion order, PHP version, locale and flags;
- **injective**: two different field sets never produce the same bytes;
- **binary-safe**: commitment bytes (32 raw bytes) are encoded without loss;
- **no plaintext personal data**: a personal field is passed as its commitment;
- **frozen once a row is anchored under it**: a change is a new class with a new `version()`, and the old class
  stays to decode the rows written under it.

Keep every version the application has ever written available to whatever decodes or re-checks a payload, keyed
by `version()` (the bundle's own verification hashes the payload as stored and needs none of them):

```php
final readonly class SerializerRegistry
{
    /** @var array<string, CanonicalSerializer> */
    private array $byVersion;

    /** @param iterable<CanonicalSerializer> $serializers */
    public function __construct(iterable $serializers, private string $current)
    {
        $byVersion = [];
        foreach ($serializers as $serializer) {
            $version = $serializer->version();
            if (isset($byVersion[$version])) {
                throw new LogicException("Two serializers claim version $version");
            }
            $byVersion[$version] = $serializer;
        }
        $this->byVersion = $byVersion;
    }

    public function forWriting(): CanonicalSerializer { return $this->byVersion[$this->current]; }

    public function forRow(string $version): CanonicalSerializer
    {
        return $this->byVersion[$version] ?? throw new UnexpectedValueException("Unknown serialization version $version");
    }
}
```

An example implementation is in [serialization-door.md](serialization-door.md).

## `AnchorWriter`

Writes the anchor of a period outside the database. Background: [anchoring.md](anchoring.md).

| Method | Contract |
|---|---|
| `write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void` | Persists the boundary hashes of one period of one segment to append-only storage. Throws if the write did not succeed. |

Invariants an adapter keeps:

- **append-only destination**: object lock in compliance mode, WORM, or at least a credential with write access
  and without delete or overwrite;
- **a credential of its own**, distinct from the backup credential and not usable to delete or overwrite anchors;
- **no silent failure**: an exception on any error, so the caller can alert;
- **no overwrite**: an object key that includes the segment and the period end, so a second write for the same
  period is a new object (or refused), never a replacement;
- **lossless**: hashes stored exactly as given; the period end stored with its time zone or in UTC.

```php
use Aws\S3\S3Client;
use Phil\AuditBundle\Domain\Contract\AnchorWriter;

final readonly class ObjectLockAnchorWriter implements AnchorWriter
{
    public function __construct(private S3Client $s3, private string $bucket) {}

    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
    {
        $end = $periodEnd->setTimezone(new DateTimeZone('UTC'));

        $this->s3->putObject([
            'Bucket' => $this->bucket,                       // bucket with object lock, compliance mode by default
            'Key' => sprintf('anchors/%s/%s.json', $segment, $end->format('Y-m-d\THis\Z')),
            'Body' => json_encode([
                'segment' => $segment,
                'period_end' => $end->format(DATE_ATOM),
                'first_hash' => bin2hex($firstHash),
                'head_hash' => bin2hex($headHash),
            ], JSON_THROW_ON_ERROR),
        ]);                                                  // the SDK throws on failure; under object lock,
                                                             // a second write is a new version, never a replacement
    }
}
```

(`aws/aws-sdk-php` is the application's dependency in this example, not the bundle's. The example assumes raw
binary hashes; drop `bin2hex()` if your chain already uses hex.)

## `AuditRowRepository`

`DbalAuditRowRepository` implements it on a table; an application that keeps the trail elsewhere implements it and
sets `connection: null`.

| Method | Contract |
|---|---|
| `first(string $segment): ?AuditRow`, `head(string $segment): ?AuditRow` | The lowest and the highest sequence of the segment, read on the key |
| `append(AuditRow $row): void` | Inserts the row; a place already taken throws `ChainConflict`, never overwrites |
| `rows(string $segment): iterable` | Every row of the segment, by sequence, streamed |
| `rowsAfter(string $segment, int $sequence, int $limit): array` | At most `$limit` rows whose sequence is above `$sequence`, by sequence, read on the key: what the feed reads |
| `segments(): array` | Every segment with a row |

## Ports of the feed

| Port | Methods | Adapters in the bundle |
|---|---|---|
| `Domain\Contract\FactExporter` | `export(non-empty-list<ExportedFact> $facts): void`, in order; throws when the batch did not land | `JsonLinesFactExporter`, `OtlpLogExporter` ([dashboards.md](dashboards.md)) |
| `Domain\Contract\FeedCheckpoints` | `position(string $feed, string $segment): int`, 0 for none; `advance(string $feed, string $segment, int $sequence): void`, never back | `DbalFeedCheckpoints` |

An exporter receives identifiers only, and is safe to call twice with the same batch: the feed sends a batch again
after a failure. A checkpoint store keeps one place per feed and segment, and ignores a sequence at or below it.

## Ports of the read side and of the context

| Port | Methods | Adapters in the bundle |
|---|---|---|
| `Domain\Contract\AuditLogReader` | `page(AuditQuery): AuditPage`, `row(string $segment, int $sequence): ?AuditRow` | `DbalAuditLogReader`, `ScanningAuditLogReader` ([reading.md](reading.md)) |
| `Application\Contract\TenantScope` | `current(): ?string`, the tenant the unit of work acts in | `TenancyTenantScope` |
| `Application\Contract\ActorResolver` | `current(): ?string`, the stable identifier of whoever acts | `SecurityActorResolver` |
| `Application\Contract\AuditNotifier` | `recorded(AuditRow): void`, once a row is in the trail | `MessengerAuditNotifier` |
| `Application\Contract\PayloadReader` | `fields(AuditRow): ?array`, the fields of a payload for a person to read, `null` for a version it does not read | none: the inverse of the application's serializer is the application's |

Two interfaces are implemented by the application's own classes:

| Interface | Implemented by | Method |
|---|---|---|
| `Application\Contract\AuditActor` | the user class | `auditActorId(): string`, its stable identifier |
| `Application\Contract\Auditable` | a workflow's subject | `auditSubject(): Subject` |

Invariants an adapter keeps:

- **identifiers only**: an actor, a tenant or a notification never carries a name or an address;
- **read only**: an `AuditLogReader` changes nothing, and gives the rows in the query's order, after its cursor, at
  most its limit, with `next` set only when a row is left;
- **the place, not the content**: an `AuditNotifier` sends where the row is, and a consumer reads it back.

A `PayloadReader` for the length-prefixed serializer of [serialization-door.md](serialization-door.md) keys its
decoders by version:

```php
final readonly class PayloadReaders implements PayloadReader
{
    /** @param array<string, Closure(string): array<string, scalar|null>> $decoders by version */
    public function __construct(private array $decoders) {}

    public function fields(AuditRow $row): ?array
    {
        return isset($this->decoders[$row->version]) ? ($this->decoders[$row->version])($row->payload) : null;
    }
}
```

## Value objects you build on

`Salt` and `Commitment` are final and not meant to be extended; compose them in your row model
([commitments-and-erasure.md](commitments-and-erasure.md)).

## Services you can rely on

`AuditTrail`, `AuditLog`, `ChainStatus`, `TrailVerification`, `Anchoring` and `DashboardFeed` are public, with the
aliases of the ports [configuration.md](configuration.md) lists. Register the adapters of the two ports the application
always writes yourself.
