# Blocks

## Saving a block

```php
$block = $blocks->save('article:42', $holder, $decoded, list: 'root', after: 'Zt0q');
$blocks->remove('article:42', $holder, 'aB3x');
```

`BlockSaves::save()` takes a block in its JSON form — `{"id", "type", "data", "tunes"}`, decoded —, checks it with
`phil/ujo` as a document of that one block, and returns it in canonical form. It is written only when the holder holds
the block's lease, or that of the whole resource; otherwise `LeaseLost`. A block `phil/ujo` refuses, or a place that
cannot be one, is `InvalidBlock`. A claim the store gave up waiting for is `LeaseHeld`, without a lease: nothing was
written, and the editor tries again.

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
use Phil\EditLockBundle\Domain\ValueObject\Fence;

final readonly class DraftBlocks implements BlockStore
{
    public function save(
        string $resource,
        Block $block,
        string $list,
        ?string $after,
        Holder $holder,
        Fence $fence,
    ): void {
        // put the block in the draft of the resource: in place of its copy, or after $after in $list
    }

    public function remove(string $resource, string $blockId, Holder $holder, Fence $fence): void
    {
        // take the block out of the draft
    }
}
```

The store is called before the save is told to the other writers.

## Writes under a lease

A lease can expire, or be taken over, at any moment. A save that checked the lease, then wrote the block, would let a
write through after the lease was lost if the request paused between the two. `BlockSaves` closes that window with
`EditLocks::whileHolding()`: the lease is checked, and the block store called, within one claim on the resource, the
same claim that acquiring, heartbeats and takeovers run in. No other claim on the resource runs between the check and
the write.

- **With the Doctrine store**, the claim is a transaction that locks the resource's row of `phil_edit_lease_resource`.
  A block store that writes on the same connection writes inside that transaction: the write is kept or undone with
  the claim, and a takeover asked meanwhile waits for it, then finds the lease to take. A block store that writes
  elsewhere still writes before any other claim starts.
- **Every write carries a `Fence`**: its key, its holder and the number of its claim, greater than that of every claim
  on the resource before it. A store that might apply a write later — on another database, through a queue, after a
  retry — keeps that number with the block and writes only when the new one is greater, by one conditional statement
  whose row count it checks:

```php
$applied = $connection->executeStatement(
    'UPDATE draft_block SET body = ?, fence = ? WHERE resource = ? AND id = ? AND fence < ?',
    [$body, $fence->number, $resource, $block->id, $fence->number],
);

if (0 === $applied) {
    // insert the block if it is new; otherwise a newer write is already kept, and this one is dropped
}
```

The claim holds the resource's row for as long as the block store takes, so a block store writes one block and
returns: the other writers of the resource wait for it, within the database's lock timeout, after which their claim
gives up and is tried again.

## The form submitted

The leases guard the saves of blocks, not the form of the page. When a writer submits the form, the submitted document
holds the blocks that writer's page had, and the application saves it as it saves any form: the last submission
wins, whatever was saved since it was loaded. With a block store, the application decides whether the submission or
the draft wins, for instance by refusing a submission whose draft changed after the page was loaded, or by keeping
the draft and ignoring the blocks the form carries.
