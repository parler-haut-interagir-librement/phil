# Configuration

```yaml
phil_edit_lock:
    seconds: 30                       # how long a lease lasts without a heartbeat, 5 to 3600
    store: doctrine                   # or "memory", for tests
    connection: default               # the Doctrine connection of the table
    holder_provider: ~                # a HolderProvider service; the endpoints need it
    policy: ~                         # an EditPolicy service; the endpoints need it
    block_store: ~                    # a BlockStore service; none only tells saves to the other writers
    mercure:
        enabled: ~                    # on when MercureBundle is registered
        hub: Symfony\Component\Mercure\HubInterface
        topic_prefix: 'urn:phil:edit-lock:'
        private: true
```

| Option | Default | Meaning |
|---|---|---|
| `seconds` | `30` | A lease's duration from its last heartbeat; the controller beats three times as often |
| `store` | `doctrine` | `doctrine` keeps leases in `phil_edit_lease`; `memory` in the process, for tests only |
| `connection` | `default` | The connection whose `doctrine.dbal.<name>_connection` service holds the table |
| `holder_provider` | none | The service that names the current writer |
| `policy` | none | The service that says who may edit and who may take over |
| `block_store` | none | The service that keeps saved blocks |
| `mercure.enabled` | MercureBundle registered | Whether presence and saves are pushed |
| `mercure.hub` | `HubInterface` | The hub's service, another hub of MercureBundle for instance |
| `mercure.topic_prefix` | `urn:phil:edit-lock:` | Put before the resource's name to make its topic |
| `mercure.private` | `true` | Private updates reach only subscribers authorized for the topic |

## Services

| Service | Class | Public |
|---|---|---|
| `Phil\EditLockBundle\Application\EditLocks` | `EditLocks` | yes |
| `Phil\EditLockBundle\Application\BlockSaves` | `BlockSaves` | yes |
| `Phil\EditLockBundle\Domain\Repository\LeaseRepository` | alias of `DoctrineLeaseRepository` or `InMemoryLeaseRepository` | yes |
| `Phil\EditLockBundle\Application\Broadcaster` | alias of `MercureBroadcaster` or `NullBroadcaster` | no |
| `Phil\EditLockBundle\Infrastructure\Http\ClientConfig` | `ClientConfig` | yes |
| `Phil\EditLockBundle\Infrastructure\Http\EditLockController` | `EditLockController` | yes |

Without `policy`, `EditLocks` uses `DefaultEditPolicy` — anyone may edit, nobody may take over — for the application's
own code; the endpoints answer `404` until both `holder_provider` and `policy` are set. `BlockSaves` checks blocks
with the `Phil\Ujo\Ujo` service of `phil/ujo-bundle` when it is registered, with the default registry of `phil/ujo`
otherwise.
