# Handlers

Each kind of resource the application trashes has a `TrashHandler`; with autoconfiguration, the bundle finds it.

| Method | What it does |
|---|---|
| `resourceKey()` | The kind: `page`, lower-case letters, digits, `_`, `.` or `-` |
| `supports($resource)` | Whether this handler keeps this object |
| `capture($resource)` | A `Captured`: the identifier, a title for the trash, what is needed to restore it — JSON —, its parent |
| `remove($resource)` | Removes the resource's rows, its own and its children's, without flushing; its files stay |
| `parentExists($parent)` | Whether a parent still exists |
| `restore($item, $parent)` | Brings the resource back under this parent, and returns it |
| `purge($item)` | Forgets what the resource left outside the trash and its tables, its files; often nothing |

```php
final readonly class PageTrashHandler implements TrashHandler
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function resourceKey(): string { return 'page'; }

    public function supports(object $resource): bool { return $resource instanceof Page; }

    public function capture(object $resource): Captured
    {
        return new Captured($resource->id, $resource->title, ['title' => $resource->title, 'body' => $resource->body], $resource->parent ? ResourceReference::of('page', $resource->parent->id) : null);
    }

    public function remove(object $resource): void { $this->entityManager->remove($resource); }

    public function parentExists(ResourceReference $parent): bool { return null !== $this->entityManager->find(Page::class, $parent->id); }

    public function restore(TrashItem $item, ?ResourceReference $parent): object
    {
        $page = Page::restore($item->resource()->id, $item->payload(), $parent?->id);
        $this->entityManager->persist($page);

        return $page;
    }

    public function purge(TrashItem $item): void {}
}
```

The trash flushes once, after the handler: the resource leaves its table and enters the trash together. A handler
never flushes, and never deletes a file in `remove()`: a restored resource needs its files back.

## A resource with files

A medium of `phil/media-bundle` keeps its files on disk. Its handler removes the row and leaves the files, and
deletes them only when the trash forgets the item for good:

```php
final readonly class MediaTrashHandler implements TrashHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private MediaStorage $storage) {}

    public function resourceKey(): string { return 'media'; }

    public function supports(object $resource): bool { return $resource instanceof Media; }

    public function capture(object $resource): Captured
    {
        return new Captured($resource->id, $resource->title() ?? $resource->file()->filename, ['storage_key' => $resource->storageKey() /* and what Media needs back */], null);
    }

    // Not MediaLibrary::delete(), which deletes the files and flushes.
    public function remove(object $resource): void { $this->entityManager->remove($resource); }

    public function parentExists(ResourceReference $parent): bool { return false; }

    public function restore(TrashItem $item, ?ResourceReference $parent): object { /* rebuild the Media from the payload */ }

    public function purge(TrashItem $item): void
    {
        $this->storage->deletePrefix('originals/'.$item->resource()->id);
        $this->storage->deletePrefix('formats/'.$item->resource()->id);
    }
}
```

## Trashing and restoring

```php
$item = $trash->trash($page, trashedBy: 'sub-1');   // the handler removes it; a TrashItem keeps it
$trash->restore($item);                             // under its parent
```

**A parent that is gone** is a `ParentMissing`, which names the resource and the parent: restore it under another
one, `$trash->restore($item, ResourceReference::of('page', '7'))`. A resource no handler supports is an
`UnknownResource`.

`TrashItemRepository::list($kind, $limit, $offset)` lists the trash, most recent first, for an administration
screen; `get($id)` finds one item.
