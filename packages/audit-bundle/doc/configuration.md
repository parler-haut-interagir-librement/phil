# Configuration

```yaml
# config/packages/phil_audit.yaml
phil_audit:
    connection: default                      # the Doctrine connection of the table; null: your own repository
    serializer: App\Audit\CanonicalSerializerV1   # your frozen CanonicalSerializer
    anchor_writer: App\Audit\ObjectLockAnchors    # to append-only storage
    anchor_reader: App\Audit\ObjectLockAnchors    # reads them back for verification
    # anchor_file: '%kernel.project_dir%/var/audit-anchors.jsonl'   # development only
    dispatch_bus: messenger.bus.default           # each recorded row dispatched as an AuditRecorded message
    workflow_segment: states                      # each transition of an Auditable subject recorded there
    site_segments: true                           # each tenant's rows in a chain of its own
    feed:                                         # the facts sent to dashboards, identifiers only
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl'
```

| Key | Default | Effect |
|---|---|---|
| `connection` | `default`; `null` without DoctrineBundle | The connection of `phil_audit_row`, through `DbalAuditRowRepository`. With `null`, the bundle registers nothing: the application gives its own `AuditRowRepository` and wires the services over it |
| `serializer` | `null` | The service of the `CanonicalSerializer` rows are written with; without it, no `AuditTrail` |
| `anchor_writer` | `null` | The `AnchorWriter`; without it (and without `anchor_file`), no `Anchoring` and no `phil:audit:anchor` |
| `anchor_reader` | `null` | The `AnchorReader` verification reads anchors back with; without it, the chain is verified without anchors |
| `anchor_file` | `null` | A `JsonLinesAnchorStore` on that file, as writer and reader when they are not given: append-only only on WORM storage, so for development and tests |
| `dispatch_bus` | `null` | The service id of a Messenger bus each recorded row is dispatched on, as an `AuditRecorded` message that carries its place only ([integrations.md](integrations.md)) |
| `workflow_segment` | `null` | The segment the completed transitions of `Auditable` workflow subjects are recorded in; it needs `serializer`, and the container build fails without it |
| `site_segments` | `false` | Whether the rows of each tenant go to a segment of that tenant's own, chained, anchored and verified on its own ([sites.md](sites.md)) |
| `feed.name` | `dashboards` | The feed's name, the key of its places |
| `feed.batch` | `500` | The rows read and sent at once, 1 to 500 |
| `feed.exporter` | `null` | The service id of a `FactExporter` of the application |
| `feed.json_lines` | `null` | A file, or a `php://` stream, the facts are appended to as JSON Lines |
| `feed.otlp.endpoint` | `null` | The logs endpoint of an OpenTelemetry collector the facts are posted to, read by `phil/url`: a host name of two labels at least, or `localhost`; a refused address stops the compilation ([dashboards.md](dashboards.md)) |
| `feed.otlp.service_name` | `symfony` | The `service.name` of the resource |
| `feed.otlp.headers` | `{}` | Headers sent with every request |
| `feed.otlp.http_client` | `http_client` | The service id of the HTTP client |
| `feed.checkpoints` | `null` | The service id of a `FeedCheckpoints`; without it, the table `phil_audit_feed` of the trail's connection |

At most one of `feed.exporter`, `feed.json_lines` and `feed.otlp.endpoint` is set; two fail the container build, and
none registers no feed ([dashboards.md](dashboards.md)).

An unknown key is refused when the container is built.

## Services wired

With a connection:

| Service id | Public | Definition |
|---|---|---|
| `DbalAuditRowRepository`, aliased `AuditRowRepository` | the alias | Over `doctrine.dbal.<connection>_connection` |
| `AuditSchemaListener` | no | `postGenerateSchema` on that connection: migrations create `phil_audit_row` and `phil_audit_feed`, the tenant, the actor and the record's identifier in the connection platform's binary collation ([reading.md](reading.md#exact-comparison)) |
| `AuditTrail` | yes | With `serializer`, the clock, the `TenantScope` and `AuditNotifier` when there are, and `site_segments` |
| `DbalAuditLogReader`, aliased `AuditLogReader` | no | Over the same connection |
| `AuditLog` | yes | With the `AuditLogReader` and the `TenantScope` when there is one ([reading.md](reading.md)) |
| `ChainStatus` | yes | With the repository, `TrailVerification` and the `TenantScope` when there is one |
| `MessengerAuditNotifier`, aliased `AuditNotifier` | no | With `dispatch_bus` |
| `TenancyTenantScope`, aliased `TenantScope` | no | When `phil/tenancy-bundle` is registered |
| `SecurityActorResolver`, aliased `ActorResolver` | no | When SecurityBundle is registered |
| `WorkflowAuditListener` | no | With `workflow_segment`, on `workflow.completed` |
| `TrailVerification` | yes | With the anchor reader, when there is one |
| `Anchoring` | yes | With the anchor writer |
| `JsonLinesAnchorStore` | no | With `anchor_file` |
| `phil:audit:verify`, `phil:audit:anchor` | — | With Symfony Console; the second with an anchor writer |
| `JsonLinesFactExporter`, `OtlpLogExporter` | no | With `feed.json_lines`, with `feed.otlp.endpoint` |
| `DbalFeedCheckpoints` | no | With a feed and no `feed.checkpoints` |
| `DashboardFeed` | yes | With a feed: the repository, the exporter, the checkpoints, `feed.name` and `feed.batch` |
| `phil:audit:feed` | — | With a feed and Symfony Console |
| `FeedOnRecorded` | no | With a feed and `dispatch_bus`: a handler of `AuditRecorded` |

Do not alias `CanonicalSerializer` to a single implementation if you expect more than one version: each row
records the version it was serialized under ([serialization-door.md](serialization-door.md)).

The partitioning of the table, the database roles that may insert and select — and never update or delete —,
retention periods, the audited events and the fields declared personal are all the application's.
