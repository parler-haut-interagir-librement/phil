# Blocks

## Saving a block

```php
$block = $blocks->save('article:42', $holder, $decoded, list: 'root', after: 'Zt0q');
$blocks->remove('article:42', $holder, 'aB3x');
```

`BlockSaves::save()` takes a block in its JSON form — `{"id", "type", "data", "tunes"}`, decoded —, checks it with
`phil/ujo` as a document of that one block, and returns it in canonical form. It is written only when the holder holds
the block's lease, or that of the whole resource; otherwise `LeaseLost`. A block `phil/ujo` refuses, or a place that
cannot be one, is `InvalidBlock`.

Its place in the document is given as the block editor names it:

| Argument | Meaning |
|---|---|
| `list` | `root`; the identifier of a block that holds blocks, a toggle for instance; or that of a block and a column, `aB3x:0` |
| `after` | The block before it in that list; `null` when it comes first |

A block that holds others — a toggle, columns — is saved with the blocks it holds.

## The application's block store

Without a store, saves are only told to the other writers: the document is written when one of them submits the
form. With one, the application keeps each block as it is saved, so that a writer who opens the page later reads the
latest blocks:

```php
use Phil\EditLockBundle\Application\BlockStore;

final readonly class DraftBlocks implements BlockStore
{
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder): void
    {
        // put the block in the draft of the resource: in place of its copy, or after $after in $list
    }

    public function remove(string $resource, string $blockId, Holder $holder): void
    {
        // take the block out of the draft
    }
}
```

The store is called before the save is told to the other writers. When the form is submitted too, the submitted
document holds the blocks of every writer that reached that page; with a store, the application decides whether the
submission or the draft wins.
