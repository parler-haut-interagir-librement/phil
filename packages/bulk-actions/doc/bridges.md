# Bridges

`Bridge\ExternalSelection` builds a selection from what another tool hands over. None of those tools is a dependency.

## Data tables

A bulk action of a data table, such as one of `phil/data-table-bundle`, receives the rows read again from its data
source, after its own `<dialog>` confirmation, its CSRF check and its voter on each row. The handler starts a run
directly; the versions the rows have now are kept, and the executor checks them again.

```php
use Phil\BulkActions\Bridge\ExternalSelection;
use Phil\BulkActions\BulkConfirmer;
use Phil\BulkActions\Change\ChangeSet;
use Phil\DataTableBundle\Application\Definition\Action;

$table->action(Action::bulk('archive', 'products.archive', 'PRODUCT_ARCHIVE', function (array $rows): void {
    $this->confirmer->start(
        $this->security->getUser()->getUserIdentifier(),
        'products',
        'archive',
        $this->selections->fromRows('products', $rows),
        ChangeSet::none(),
        new DateTimeImmutable(),
    );
})->destructive());
```

`fromRows()` refuses a row that is not an object of the target's class, such as the array row of a query.

## Administration batch actions

A batch action of an administration, such as EasyAdmin's `BatchActionDto`, gives identifiers and an entity class. The
class is compared with the target's — the same class or a subclass — and never used to find the items. The controller
then sends the person to the preview.

```php
public function bulkEdit(BatchActionDto $batch, BulkSelector $selector, ExternalSelection $selections): Response
{
    $selection = $selections->fromBatch('products', $batch->getEntityFqcn(), $batch->getEntityIds());
    $token = $selector->select($this->getUser()->getUserIdentifier(), 'products', 'edit', $selection, new DateTimeImmutable());

    return $this->redirectToRoute('admin_bulk_preview', ['token' => $token->value]);
}
```

The batch action's own CSRF token is checked by the administration before the controller runs.

## The trash

A bulk delete is an action whose `apply()` moves the item to the trash, through `phil/trash-bundle` when the application
has it, and which says `isDestructive()`. The library offers no undo by delay: the trash is the way back.

## The audit trail

`Contract\AuditTrail` is told of each run's confirmation, end, stop and retry, with the run: who, which action, on how
many items. An adapter to `phil/audit-bundle` writes one entry per event; see [Extension points](extension-points.md).
