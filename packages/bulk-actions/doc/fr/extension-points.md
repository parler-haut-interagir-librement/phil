<!-- phil-dev source-sha256=5a85bfd04a7d614ba0b953e3f01923c40628a0314eac0894106a20d8012f8bfb -->
# Points d'extension

La bibliothèque en est à ses versions `0.x` : les ports, les objets-valeurs et les exceptions peuvent changer dans une
version mineure, ce que dit le `CHANGELOG`. Ils sont destinés à être stables à partir de `1.0`.

## Ports

| Port | Implémenté par | Rôle |
|---|---|---|
| `Contract\BulkAction` | l'application, une classe par action | nomme l'action, son attribut de voter et ses champs, et modifie un élément |
| `Contract\ItemRepository` | un adaptateur par cible, au-dessus de l'ORM ou d'un constructeur de requêtes | charge les éléments, lit les filtres lot par lot, donne les identifiants et les versions |
| `Contract\Authorizer` | un adaptateur vers les règles d'accès | dit si une personne peut lancer une action, et l'appliquer à un élément |
| `Contract\SelectionStore` | une table ou un cache partagé | garde les sélections en attente, et en prend une de façon atomique |
| `Contract\RunStore` | une table, idéalement dans la base des éléments | garde les exécutions entre les lots |
| `Contract\Transactions` | un adaptateur vers la base de données | valide un lot, ou l'annule et oublie ce que la mémoire retient |
| `Contract\RunDispatcher` | un adaptateur vers le bus | demande le lot suivant |
| `Contract\AuditTrail` | un adaptateur vers le journal d'audit, facultatif | enregistre les confirmations, les fins, les arrêts et les nouvelles tentatives |

`InMemorySelectionStore` et `InMemoryRunStore` gardent dans un tableau ce que donne `toArray()`, pour les tests et un
seul processus.

## Le dépôt

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

Un filtre n'est jamais transformé aveuglément en requête : `supports()` n'accepte que les noms et les paramètres que le
dépôt connaît, et la requête les lie comme paramètres.

## Les règles d'accès

Avec Symfony Security, les voters sont interrogés pour la personne qui a confirmé, par son identifiant, puisqu'un
*worker* n'a aucun utilisateur connecté :

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

Un vrai adaptateur garde l'utilisateur qu'il a chargé pendant la durée d'un seul appel à l'exécuteur, pas d'un message à
l'autre, afin de rester sûr dans un *worker*.

## Les magasins

Un magasin de sélections a besoin d'un `take()` atomique : un `DELETE … RETURNING`, ou un `SELECT … FOR UPDATE` suivi
d'un `DELETE` dans une seule transaction, ou le `GETDEL` d'un cache. La ligne contient `PendingSelection::toArray()`
encodé en JSON, avec l'empreinte pour clé et l'expiration dans une colonne, afin qu'une tâche planifiée supprime les
lignes expirées.

Un magasin d'exécutions garde `BulkRun::toArray()` en JSON, avec l'identifiant de l'exécution pour clé. Gardé dans la
base des éléments, l'enregistrement que l'exécuteur fait dans la transaction d'un lot valide les résultats avec les
changements : un *worker* arrêté entre les deux ne laisse rien d'appliqué deux fois.

## La piste d'audit

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

## Les pages

La bibliothèque ne produit aucun rendu. Les contrôleurs, le gabarit du `<dialog>` et les traductions des clés de
résultat appartiennent à l'application ; [Aperçu et confirmation](confirmation.md) donne un gabarit de départ.
