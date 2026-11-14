# Feeding dashboards

The trail is the record; a dashboard is a view of it. The feed sends the facts of each row — where it is, when, what
kind, the action, the actor, the record, the tenant and the hash — to a destination a dashboard reads: a file a log
shipper tails, an OpenTelemetry collector, or an exporter of your own. **It sends identifiers only**: never the
payload, so that no field of the trail, personal or not, leaves it. A person who needs the fields reads the row where
it is, through `AuditLog::row()`.

## Turning it on

One exporter per feed:

```yaml
phil_audit:
    feed:
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl'     # or php://stdout in a container
```

```yaml
phil_audit:
    feed:
        name: siem                                            # the key of its places; default "dashboards"
        batch: 500                                            # rows read and sent at once, 1 to 500
        otlp:
            endpoint: 'https://collector.internal:4318/v1/logs'
            service_name: shop
            headers: { Authorization: '%env(OTEL_COLLECTOR_AUTHORIZATION)%' }
            http_client: http_client
```

```yaml
phil_audit:
    feed:
        exporter: App\Audit\WarehouseExporter                 # a FactExporter of yours
        checkpoints: App\Audit\RedisFeedPlaces                # a FeedCheckpoints of yours; default, a table
```

Setting two of `exporter`, `json_lines` and `otlp.endpoint` fails the container build. Without any, no feed is
registered.

## How it runs

`DashboardFeed` keeps a place per segment: the sequence of the last row it sent. A run reads the rows after that place
on the `(segment, sequence)` key, a batch at a time, sends each batch through the exporter, and moves the place past
it once the exporter returned. The sequence of a segment has no gap and never changes, so a row recorded late, or with
an instant earlier than the rows around it, is never skipped — which a feed keyed on instants could not promise.

- **At least once.** A batch whose sending throws (`ExportFailed`) leaves the place where it was: the next run sends
  it again. A receiver that keeps the pair `segment` and `sequence` drops a row it already has.
- **Bounded.** `feed(?array $segments = null, int $max = PHP_INT_MAX)` sends at most `$max` rows; the next run
  carries on where it stopped. `lag($segment)` says how many rows of a segment wait, read on the key.
- **Named.** Two feeds to two destinations keep two sets of places, by their `name`.

Run it from a schedule:

```bash
bin/console phil:audit:feed --max=100000         # every segment
bin/console phil:audit:feed orders members       # those segments only
```

Or after each row: with `dispatch_bus` set, the bundle registers a handler, `FeedOnRecorded`, that feeds the segment
of each `AuditRecorded` message. Route the message to an asynchronous transport, and the dashboards follow the trail
within seconds without slowing the request that recorded the row. A message handled twice, or late, sends nothing
the place has moved past.

```yaml
phil_audit:
    dispatch_bus: messenger.bus.default
    feed:
        otlp: { endpoint: 'https://collector.internal:4318/v1/logs', service_name: shop }

framework:
    messenger:
        routing:
            Phil\AuditBundle\Application\Message\AuditRecorded: async
```

Two runs of the same feed at the same time may send a batch twice; the receiver's deduplication covers it. To avoid
the double sending, keep one consumer on the transport, or run the command under a lock.

## What is sent

`ExportedFact::toArray()`, every key present, `null` for a fact the row does not have:

| Key | Value |
|---|---|
| `segment`, `sequence` | The row's place: its identity |
| `recorded_at` | The instant, UTC, to the microsecond, as the trail keeps it |
| `kind` | `event` or `discontinuity` |
| `action`, `actor`, `subject_type`, `subject_id`, `tenant` | The facts, identifiers only |
| `hash` | The row's hash, for a receiver that checks a row against the trail |

### JSON Lines

`JsonLinesFactExporter` appends one JSON object per line, each batch in one write under an exclusive lock, slashes
and non-ASCII characters unescaped:

```json
{"segment":"orders","sequence":1,"recorded_at":"2026-11-12T09:00:00.000000Z","kind":"event","action":"order.paid","actor":"acct-7","subject_type":"order","subject_id":"42","tenant":"shop-a","hash":"…"}
```

A `php://` stream, such as `php://stdout`, is written without the lock, which only a file takes. A write that fails
throws `ExportFailed`.

### OpenTelemetry logs

`OtlpLogExporter` posts each batch to the collector's logs endpoint as OTLP over HTTP in its JSON encoding, with a
timeout of 10 seconds: one resource whose `service.name` is `service_name`, one scope, `phil/audit-bundle`, and a log
record per fact. A record's `timeUnixNano` is the row's instant, its severity `INFO` (9), its body the action — the
kind of row when it has none —, and its attributes the facts under `phil.audit.*`: `phil.audit.segment`,
`phil.audit.sequence` (an integer), `phil.audit.kind`, `phil.audit.action`, `phil.audit.actor`,
`phil.audit.subject_type`, `phil.audit.subject_id`, `phil.audit.tenant` and `phil.audit.hash`, each present only
when the row has it. An answer other than 2xx, or none, throws `ExportFailed`. Redirects are never followed
(`max_redirects: 0`): a collector that answers `3xx` fails the batch, so that the facts and the headers, an
authorization among them, never reach another host. The exporter needs `symfony/http-client` and `phil/url`.

The endpoint is read by `phil/url` as the container compiles, and a refused one stops it: `http` or `https`, a host
name of two labels at least, such as `collector.internal`, or `localhost` for a collector beside the application. An
address with a user name or a password, an IP address, or a single name such as a container's service name is
refused; give the collector a name of two labels, through the network's DNS or an alias.

### An exporter of your own

```php
use Phil\AuditBundle\Domain\Contract\FactExporter;

final readonly class WarehouseExporter implements FactExporter
{
    public function __construct(private Connection $warehouse) {}

    public function export(array $facts): void
    {
        $this->warehouse->transactional(function () use ($facts): void {
            foreach ($facts as $fact) {
                // INSERT … ON CONFLICT (segment, sequence) DO NOTHING: a batch sent again changes nothing.
                $this->warehouse->executeStatement('INSERT INTO audit_facts …', $fact->toArray());
            }
        });
    }
}
```

Throw when the batch did not land: the feed keeps its place.

## The places

By default the places live in `phil_audit_feed` on the trail's connection — one row per feed and segment, its
primary key `(feed, segment)` —, which `AuditSchemaListener` adds to the schema with the trail's table. A place only
moves forward: `DbalFeedCheckpoints::advance()` never writes a sequence lower than the one stored. The database role
of the feed needs to insert, select and update that table, and nothing on the trail's table but select.
