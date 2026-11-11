# Extension points

What an application implements, configures or relies on. Blocks are read in UJO documents.

## Ports written by the application

Each is named by its service id in the configuration — see [Configuration](configuration.md). The endpoints answer
`404` until both `holder_provider` and `policy` are set.

### `HolderProvider`

`Phil\EditLockBundle\Application\HolderProvider` — who writes in the current request (`holder_provider`).

| Method | Contract |
|---|---|
| `holder(): ?Holder` | The current writer, an identifier and the name shown to the others, or `null` for nobody |

### `EditPolicy`

`Phil\EditLockBundle\Application\EditPolicy` — what a writer may edit and take over (`policy`). Without one,
`EditLocks` uses `DefaultEditPolicy` — anyone may edit, nobody may take over — for the application's own code. See
[Leases](leases.md).

| Method | Contract |
|---|---|
| `mayEdit(Holder $holder, string $resource): bool` | Guards acquiring, taking over, heartbeats, block writes and reading presence |
| `mayTakeOver(Holder $holder, Lease $lease): bool` | Asked for each lease a takeover would remove |

### `BlockStore`

`Phil\EditLockBundle\Application\BlockStore` — where saved blocks are kept (`block_store`). Without one, saves are
only told to the other writers. See [Blocks](blocks.md).

| Method | Contract |
|---|---|
| `save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void` | Puts the block in the resource's draft, in place of its copy or after `$after` in `$list`; called before the save is told to the others |
| `remove(string $resource, string $blockId, Holder $holder): void` | Takes the block out of the draft |

## Ports with shipped adapters

### `Broadcaster`

`Phil\EditLockBundle\Application\Broadcaster` — tells the other writers what changed. `MercureBroadcaster` with
MercureBundle, `NullBroadcaster` without it; an application points the alias at its own — another push service.

| Method | Contract |
|---|---|
| `presence(string $resource, list<Lease> $leases, DateTimeImmutable $now): void` | The leases active on the resource, after one changed |
| `saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void` | A block saved, as a document of that one block |
| `removed(string $resource, Holder $holder, string $blockId): void` | A block removed |

### `LeaseRepository`

`Phil\EditLockBundle\Domain\Repository\LeaseRepository` — the leases; Doctrine or memory, by `store`. A store of the
application's own keeps the guarantees of each method: `exclusively()` runs a claim while no other claim on the
resource runs, `insert()` keeps a lease only when none is on its key, `replace()` only when the version read is still
the one kept, `delete()` only for the holder who holds it.

## Configuration

| Option | What it extends |
|---|---|
| `seconds` | A lease's duration from its last heartbeat |
| `mercure.hub`, `topic_prefix`, `private` | Which hub, topics and privacy the updates use — see [Mercure](mercure.md) |

## The browser

| Point | How |
|---|---|
| Labels | `messages` in the controller's configuration, by key: `presence`, `editing`, `takeOver` and the others |
| The look | `assets/styles/edit-lock.css` under `.phil-edit-lock-roster`, with `--pel-text`, `--pel-accent` and the others |
| The block editor | The controller dispatches `phil-block-editor:apply`, `phil-block-editor:remove` and `phil-block-editor:lock` on the editor's element; any editor that listens to them works with it |

See [JavaScript](javascript.md).
