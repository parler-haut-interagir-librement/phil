# Extension points

What an application implements, listens to, configures or relies on. Resources are `phil/resource` references.

## `TrashHandler`

`Phil\TrashBundle\Application\TrashHandler` — one kind of resource the application trashes. A service implementing it
is tagged `phil_trash.handler` by autoconfiguration. See [Handlers](handlers.md).

| Method | Contract |
|---|---|
| `resourceKey(): string` | The kind: lower-case letters, digits, `_`, `.` or `-` |
| `supports(object $resource): bool` | Whether it keeps this object |
| `capture(object $resource): Captured` | The identifier, a title for the trash, what is needed to restore it as JSON, and its parent |
| `remove(object $resource): void` | Removes the resource's rows, its own and its children's, without flushing; its files stay |
| `parentExists(ResourceReference $parent): bool` | Whether a parent still exists |
| `restore(TrashItem $item, ?ResourceReference $parent): object` | Brings the resource back under this parent, without flushing, and returns it |
| `purge(TrashItem $item): void` | Forgets what the resource left outside the trash and its tables — its files —; often nothing |

The trash flushes once, after the handler: a handler never flushes, and never deletes a file before `purge()`.

## Events

| Event | When | What a listener does |
|---|---|---|
| `Phil\TrashBundle\Application\Event\ResourceTrashed` | A resource enters the trash | Writes the audit trail, clears a cache |
| `Phil\TrashBundle\Application\Event\ResourceRestored` | It comes back | The same |
| `Phil\TrashBundle\Application\Event\TrashItemPurged` | It is forgotten for good | The same |

They are dispatched by the application's event dispatcher, when there is one. See [Retention](retention.md#events).

## Configuration

| Option | What it extends |
|---|---|
| `retention` | How many days a trashed resource is kept before it is purged |
| `retention_by_kind` | Days kept for some kinds: `{ page: 90 }` |

## `TrashItemRepository`

`Phil\TrashBundle\Domain\Repository\TrashItemRepository` — the trash, a public alias of `DoctrineTrashItemRepository`.
A store of the application's own keeps `expired()` bounded by its limit, so that `phil:trash:purge` never reads the
whole trash.
