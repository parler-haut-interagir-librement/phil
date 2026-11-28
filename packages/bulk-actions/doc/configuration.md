# Configuration

## Targets

A target is a kind of item: its name, the class of its items, its repository and its actions. Requests name the target
and the action; the class is never read from a request.

```php
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;

$targets = new Targets([
    new Target('products', Product::class, $productRepository, [$editProducts, $archiveProducts]),
    new Target('members', Member::class, $memberRepository, [$moveToSegment]),
]);
```

- A name is a letter followed by letters, digits, `_`, `-` or `.`, 64 bytes at most; so is an action's name and each of
  its fields.
- A target and an action are declared once; `Targets::get()` and `Target::action()` throw `UnknownAction` otherwise.
- `Target::holds()` tells whether an item is an instance of the target's class, an interface included. The executor and
  the preview treat any other object as missing.

## Actions

An action implements `Contract\BulkAction`:

```php
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Run\ItemContext;

final readonly class EditProducts implements BulkAction
{
    public function __construct(private Outbox $outbox)
    {
    }

    public function name(): string { return 'edit'; }

    public function attribute(): string { return 'PRODUCT_EDIT'; }

    public function isDestructive(): bool { return false; }

    public function fields(): array { return ['status', 'price']; }

    public function label(object $item): string { return $item->name(); }

    public function values(object $item): array
    {
        return ['status' => $item->status()->value, 'price' => $item->price()?->amount()];
    }

    public function apply(object $item, ChangeSet $changes, ItemContext $context): void
    {
        if ($item->isLocked()) {
            throw ItemRefused::because('products.refused.locked');
        }

        if ($changes->has('status')) {
            $item->moveTo(ProductStatus::from((string) $changes->value('status')));
        }

        if ($changes->has('price')) {
            $item->reprice($changes->value('price'));   // null empties the price
        }

        $this->outbox->record('product.changed', ['id' => $context->item], idempotencyKey: $context->idempotencyKey());
    }
}
```

| Method | Role |
|---|---|
| `name()` | the name a request uses |
| `attribute()` | the voter attribute asked on each item |
| `isDestructive()` | the preview warns of an action that removes or overwrites |
| `fields()` | the fields a change may set; an empty list for an action without fields, such as an archive |
| `label()`, `values()` | what the preview shows of a sampled item: its label, then its current values |
| `apply()` | changes one item inside its chunk's transaction; throws `ItemRefused` or `VersionConflict` for one item |

An edit names at least one of the action's fields, and nothing else; an action without fields takes
`ChangeSet::none()`. A change value is a scalar, `null` or a list of them: the action turns it into an enumeration, a
date or a money amount.

## Limits

`Limits` bounds every step; the same instance is given to every service.

| Setting | Default | Bounds | Meaning |
|---|---|---|---|
| `maxIdentifiers` | 1,000 | 1–10,000 | identifiers a page may send, explicit or unticked |
| `maxItems` | 10,000 | 1–1,000,000 | items a filter may match, at the selection and while the run reads it |
| `chunkSize` | 100 | 1–1,000 | items per message and per transaction |
| `sampleSize` | 10 | 0–100 | items the preview shows |
| `lifetimeSeconds` | 900 | 60–86,400 | how long a selection waits for its confirmation |

A larger explicit selection is refused with a hint to select by filter; a larger filter is refused with a hint to narrow
it. A filter that grows past `maxItems` while the run reads it stops there, and the report says it was truncated.
