# Collectors and targets

## A collector

A `ReferenceCollector` finds what one kind of source links to. The field is where the link is: a property, a path
into a JSON document — `blocks.2.image` —, letters, digits, `_`, `.`, `:`, `-` and brackets.

```php
final readonly class PageReferences implements ReferenceCollector
{
    public function supports(object $source): bool { return $source instanceof Page; }

    public function sourceOf(object $source): ResourceReference { return ResourceReference::of('page', $source->id); }

    public function collect(object $source): iterable
    {
        foreach ($source->blocks as $index => $block) {
            if (isset($block['image'])) {
                yield new Link('blocks.'.$index.'.image', ResourceReference::of('image', $block['image']));
            }
        }
    }
}
```

## A target

A `ReferenceTarget` knows one kind of resource others use: whether one still exists, and which reference an object
is. Without one, a kind is never refused a deletion nor checked for broken links.

```php
final readonly class ImageReferences implements ReferenceTarget
{
    public function kind(): string { return 'image'; }

    public function exists(string $id): bool { return null !== $this->images->find($id); }

    public function referenceOf(object $resource): ?ResourceReference
    {
        return $resource instanceof Image ? ResourceReference::of('image', $resource->id) : null;
    }
}
```

With autoconfiguration, the bundle finds both.

## What a flush does

With `track_entities`, `ReferenceTrackingListener` watches the flushes of its connection:

- before the flush, **a target still used by a source that stays refuses its deletion**: `StillReferenced`, and the
  flush is rolled back. A source deleted in the same flush does not count;
- after the flush, a source inserted or updated has its links collected again, a source deleted has them forgotten,
  in the order of the flushes. Only the links that changed are written: a source saved without a change to its
  links costs one read.

A deleted source's reference is read before the flush, while its identifier is known: Doctrine empties a generated
identifier once the row is deleted. A flush that fails closes its entity manager, and what it would have recorded is
dropped with it; clearing the entity manager, or the container's `kernel.reset` between a worker's messages, drops it
too.

Call `References::assertUnused()` before deleting, to show the person a message rather than a rolled-back flush;
the listener is the safety net. For sources that are not Doctrine entities, call `refresh()` and `forget()`
yourself.
