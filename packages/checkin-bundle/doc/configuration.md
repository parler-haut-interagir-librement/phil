# Configuration

```yaml
phil_checkin:
    enabled: false                 # the endpoints answer 404 until true
    clock: clock                   # the PSR-20 clock scans are timed on
    store: doctrine                # "doctrine", "memory" for tests, or the service id of a CheckInLedger
    connection: default            # the Doctrine connection of the table
    catalog: null                  # the service id of a CredentialCatalog; null asks the tagged catalogs
    policy:
        early_seconds: 0           # 0 to 86400: how early before its opening a credential is let in
        anti_passback: true        # whether a holder inside must check out before coming in again
    rules:
        checkpoints: {}            # the ExpressionLanguage rule of each checkpoint, by its name
        provider: null             # the service id of an AdmissionRules that gives the rules instead
        attributes: null           # the service id of a CredentialAttributes the rules read
        timezone: null             # the time zone of `now` in the rules; null keeps the clock's
    offline:
        manifest_seconds: 43200    # 60 to 604800: how long a device may use a manifest
        max_credentials: 10000     # 1 to 100000: how many credentials a manifest carries
        labels: false              # whether manifests carry the labels, such as the holders' names
        min_code_bits: 64          # 0 to 128: the strength a code needs to be carried by a manifest; 0 takes any
        batch_limit: 500           # 1 to 500: how many scans a device sends at once
        clock_skew_seconds: 300    # 0 to 3600: how far ahead of the server a device clock may be
        max_age_seconds: 172800    # 60 to 2592000: how old a scan made offline may be
```

| Key | Effect |
|---|---|
| `enabled` | Off, every endpoint answers `{"error": "disabled"}` with 404; the services still exist for the application's own use. |
| `store` | `doctrine` registers `DoctrineCheckInLedger` on `connection`, and its schema listener when the ORM is installed. `memory` keeps check-ins in the process, for tests. Any other value is the service id of the application's ledger. The ledger is aliased as `CheckInLedger`. |
| `catalog` | The catalog of the offline devices: it gives `CheckpointDesks` its manifests, registers `OfflineSync`, opens the manifest and synchronisation endpoints, and tells the controller it may go offline. Null asks together the services tagged `phil_checkin.credential_catalog`, which autoconfiguration tags; without any, the desks check in online only and those endpoints answer 404. |
| `policy` | The `AdmissionPolicy` of the desk, the manifests and the synchronisation. |
| `rules` | The admission rule of each checkpoint, below. |
| `offline` | The bounds of `ManifestIssuer`, `OfflineSync` and of a journal's length. A manifest carries its key, so `min_code_bits` keeps out codes short enough to be guessed from a lost device: lower it only for codes known to be random. |

A value out of its bounds fails the container build with its path.

## Rules

A checkpoint's rule is an expression of Symfony's ExpressionLanguage that a credential meets to enter there, on top of
the policy:

```yaml
phil_checkin:
    rules:
        timezone: America/Toronto
        attributes: App\Checkin\TicketAttributes
        checkpoints:
            bar: "attributes['age'] >= 18"
            workshop: "'workshop' in attributes['products'] and now.format('H') >= '09'"
            backstage: "credential.label != null and checkpoint == 'backstage'"
```

| Variable | What it is |
|---|---|
| `credential` | The `Phil\Checkin\Credential`: `credential.id`, `credential.label`, `credential.maxEntries`, `credential.validFrom`, `credential.validUntil`, `credential.status`. |
| `attributes` | What the `CredentialAttributes` named by `attributes` answers for the credential; an empty array without one. |
| `checkpoint` | The checkpoint's name. |
| `now` | The clock's instant in `timezone`, a `DateTimeImmutable`: `now.format('H')` is the hour, `now.format('N')` the day of the week, 1 for Monday. |

A credential enters only when its rule evaluates to `true` itself. Refused, it is answered `wrong_checkpoint`, recorded
like any refusal, and left out of the checkpoint's offline manifest. A rule applies to entries only: a holder inside
always checks out.

Online, a rule is evaluated at each scan; offline, when the manifest is issued — a rule on the hour closes the door
offline only at the next manifest, a minute after it closes online. A journal synchronised is recorded with the
device's decisions, which the rule had filtered.

Each rule of the configuration is checked when the container compiles: a rule longer than 1000 bytes, one that does
not parse, or one that reads another variable stops the build with the checkpoint's name. A rule that fails on a
credential — an attribute missing, a method that throws — refuses it. `rules.provider` replaces the configuration: see
[Extension points](extension-points.md#admission-rules).
