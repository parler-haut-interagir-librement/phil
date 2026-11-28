# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\BulkAction` | the application, one class per action | names the action, its voter attribute and fields, and changes one item |
| `Contract\ItemRepository` | an adapter per target, over the ORM or a query builder | loads items, reads filters chunk by chunk, gives identifiers and versions |
| `Contract\Authorizer` | an adapter to the access rules | says whether a person may run an action, and apply it to an item |
| `Contract\SelectionStore` | a table or a shared cache | keeps the selections waiting, and takes one atomically |
| `Contract\RunStore` | a table, best in the items' database | keeps the runs between chunks |
| `Contract\Transactions` | an adapter to the database | commits a chunk, or rolls it back and forgets what memory holds |
| `Contract\RunDispatcher` | an adapter to the bus | asks for the next chunk |
| `Contract\AuditTrail` | an adapter to the audit log, optional | records confirmations, ends, stops and retries |

`InMemorySelectionStore` and `InMemoryRunStore` keep what `toArray()` gives in an array, for tests and a single
process.

## The repository

```php
use Doctrine\ORM\EntityManagerInterface;
use Phil\BulkActions\Contract\ItemRepository;
use Phil\BulkActions\Selection\FilterCriteria;

final readonly class ProductItems implements ItemRepository
{
    public function __construct(private EntityManagerInterface $entities)
    {
    }

    public function find(array $identifiers): array
    {
        $found = [];

        foreach ($this->entities->getRepository(Product::class)->findBy(['id' => $identifiers]) as $product) {
            $found[$product->id()] = $product;
        }

        return $found;
    }

    public function supports(FilterCriteria $filter): bool
    {
        return 'status' === $filter->name && null !== ProductStatus::tryFrom((string) ($filter->parameters['status'] ?? ''));
    }

    public function count(FilterCriteria $filter, array $excluded): int
    {
        return (int) $this->query($filter, $excluded)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    public function matching(FilterCriteria $filter, array $excluded, ?string $after, int $limit): array
    {
        $query = $this->query($filter, $excluded)->select('p.id')->orderBy('p.id')->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere('p.id > :after')->setParameter('after', $after);
        }

        return array_map(strval(...), $query->getQuery()->getSingleColumnResult());
    }

    public function identify(object $item): string
    {
        return $item->id();
    }

    public function version(object $item): ?string
    {
        return (string) $item->version();
    }

    private function query(FilterCriteria $filter, array $excluded): QueryBuilder
    {
        $query = $this->entities->createQueryBuilder()->from(Product::class, 'p')
            ->where('p.status = :status')->setParameter('status', $filter->parameters['status']);

        return [] === $excluded ? $query : $query->andWhere('p.id NOT IN (:excluded)')->setParameter('excluded', $excluded);
    }
}
```

A filter is never turned into a query blindly: `supports()` accepts only the names and parameters the repository
knows, and the query binds them as parameters.

## The access rules

With Symfony Security, the voters are asked for the person who confirmed, by identifier, since a worker has no logged-in
user:

```php
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\BulkAction;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class SecurityAuthorizer implements Authorizer
{
    public function __construct(
        private UserProviderInterface $users,
        private UserAuthorizationCheckerInterface $checker,
    ) {
    }

    public function mayRun(string $actor, string $target, BulkAction $action): bool
    {
        return $this->checker->isGrantedForUser($this->users->loadUserByIdentifier($actor), 'BULK_'.strtoupper($action->name()), $target);
    }

    public function mayApply(string $actor, BulkAction $action, object $item): bool
    {
        return $this->checker->isGrantedForUser($this->users->loadUserByIdentifier($actor), $action->attribute(), $item);
    }
}
```

A real adapter keeps the user it loaded for the length of one call to the executor, not across messages, so that it
stays safe in a worker.

## The stores

A selection store needs an atomic `take()`: a `DELETE … RETURNING`, or a `SELECT … FOR UPDATE` followed by a `DELETE`
in one transaction, or a cache's `GETDEL`. The row holds `PendingSelection::toArray()` encoded as JSON, keyed by the
fingerprint, with the expiry in a column so that a scheduled task deletes the expired rows.

A run store keeps `BulkRun::toArray()` as JSON, keyed by the run's identifier. Kept in the database of the items, the
save the executor makes inside a chunk's transaction commits the outcomes with the changes: a worker stopped between
the two leaves nothing applied twice.

## The audit trail

```php
use Phil\BulkActions\Contract\AuditTrail;
use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;

final readonly class AuditTrailAdapter implements AuditTrail
{
    public function __construct(private AuditLog $log)   // the application's audit service
    {
    }

    public function record(AuditEvent $event, BulkRun $run): void
    {
        $this->log->write('bulk_action.'.$event->value, $run->actor, [
            'run' => $run->id->value,
            'target' => $run->target,
            'action' => $run->action,
            'items' => $run->size(),
            'counts' => $run->report()->counts(),
        ]);
    }
}
```

## The pages

The library renders nothing. The controllers, the `<dialog>` template and the translations of the outcome keys belong
to the application; [Preview and confirmation](confirmation.md) gives a template to start from.
