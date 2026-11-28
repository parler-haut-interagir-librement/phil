<!-- phil-dev source-sha256=c8c9c47f8ac570df550f2da5cffa35faba8950721a3e06522b97aa70f4ee98df -->
# Configuration

## Cibles

Une cible est un genre d'élément : son nom, la classe de ses éléments, son dépôt et ses actions. Les requêtes nomment la
cible et l'action ; la classe n'est jamais lue dans une requête.

```php
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;

$targets = new Targets([
    new Target('products', Product::class, $productRepository, [$editProducts, $archiveProducts]),
    new Target('members', Member::class, $memberRepository, [$moveToSegment]),
]);
```

- Un nom est une lettre suivie de lettres, de chiffres, de `_`, de `-` ou de `.`, 64 octets au plus ; il en va de même
  pour le nom d'une action et pour chacun de ses champs.
- Une cible et une action sont déclarées une seule fois ; sinon `Targets::get()` et `Target::action()` lancent
  `UnknownAction`.
- `Target::holds()` dit si un élément est une instance de la classe de la cible, interface comprise. L'exécuteur et
  l'aperçu traitent tout autre objet comme manquant.

## Actions

Une action implémente `Contract\BulkAction` :

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

| Méthode | Rôle |
|---|---|
| `name()` | le nom qu'utilise une requête |
| `attribute()` | l'attribut de voter demandé sur chaque élément |
| `isDestructive()` | l'aperçu avertit d'une action qui supprime ou écrase |
| `fields()` | les champs qu'une modification peut définir ; une liste vide pour une action sans champ, comme un archivage |
| `label()`, `values()` | ce que l'aperçu montre d'un élément de l'échantillon : son libellé, puis ses valeurs actuelles |
| `apply()` | modifie un élément dans la transaction de son lot ; lance `ItemRefused` ou `VersionConflict` pour un élément |

Une modification nomme au moins un des champs de l'action, et rien d'autre ; une action sans champ prend
`ChangeSet::none()`. Une valeur de modification est un scalaire, `null` ou une liste de ceux-ci : l'action la transforme
en énumération, en date ou en montant d'argent.

## Limites

`Limits` borne chaque étape ; la même instance est donnée à chaque service.

| Réglage | Valeur par défaut | Bornes | Signification |
|---|---|---|---|
| `maxIdentifiers` | 1 000 | 1–10 000 | identifiants qu'une page peut envoyer, explicites ou décochés |
| `maxItems` | 10 000 | 1–1 000 000 | éléments qu'un filtre peut trouver, à la sélection et pendant que l'exécution le lit |
| `chunkSize` | 100 | 1–1 000 | éléments par message et par transaction |
| `sampleSize` | 10 | 0–100 | éléments que montre l'aperçu |
| `lifetimeSeconds` | 900 | 60–86 400 | durée d'attente d'une sélection avant sa confirmation |

Une sélection explicite plus grande est refusée avec une suggestion de sélectionner par filtre ; un filtre plus grand
est refusé avec une suggestion de le resserrer. Un filtre qui dépasse `maxItems` pendant que l'exécution le lit s'arrête
là, et le rapport dit qu'il a été tronqué.
