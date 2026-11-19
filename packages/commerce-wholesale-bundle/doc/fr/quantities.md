<!-- phil-dev source-sha256=cb2a2982be320248fed9fd807cbd21f48716a3eb2702c9c3f02db96ea594117a -->
# Règles de quantité

Un *SKU* peut ne se vendre qu'à partir d'un minimum, jusqu'à un maximum et par tranches d'un incrément, pour tous les
acheteurs ou pour les acheteurs d'un groupe de clients. Une règle est une contrainte du Validator de Symfony : le
formulaire d'une application montre donc son message avant de toucher au panier, et le noyau la vérifie de nouveau
chaque fois qu'une ligne est ajoutée, que sa quantité change ou que le groupe de la commande change.

```php
use Phil\CommerceWholesaleBundle\Application\QuantityRules;

$rules->set('DICE-D20', null, minimum: 1, maximum: 50);                       // every buyer
$rules->set('DICE-D20', 'wholesale', minimum: 12, maximum: null, increment: 12); // the wholesale group

$rules->ruleFor('DICE-D20', 'wholesale');   // the group's own rule
$rules->ruleFor('DICE-D20', 'retail');      // the rule for every group
$rules->remove('DICE-D20', 'wholesale');
```

| Membre de `QuantityRule` | Règle |
|---|---|
| `sku`, `customerGroup` | 1 à 64 caractères ASCII visibles ; un groupe `null` vaut pour chaque groupe sans règle propre |
| `minimum()` | 1 à 1 000 000 unités |
| `maximum()` | Au moins le minimum, au plus 1 000 000 000 ; `null` pour aucune limite |
| `increment()` | 1 à 1 000 000 ; les tranches se comptent à partir du minimum : un minimum de 6 et un incrément de 6 permettent 6, 12, 18 |
| `breachOf(quantity)` | `BelowMinimum`, `AboveMaximum`, `OffIncrement`, la première règle enfreinte ; `null` quand la quantité est permise |
| `nextAllowed(quantity)` | La quantité permise la plus proche, égale ou supérieure ; `null` quand le maximum l'empêche |
| `change()` | De nouvelles conditions, vérifiées comme celles d'une nouvelle règle |

La règle propre à un groupe remplace la règle de tous les groupes, elle ne s'y ajoute pas. Un *SKU* sans règle prend
n'importe quelle quantité.

## La contrainte

`AllowedQuantity` valide une `RequestedQuantity` — le *SKU*, la quantité, le groupe de clients. L'objet de formulaire
d'une application en retourne une par un accesseur qui porte la contrainte :

```php
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantity;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\RequestedQuantity;

final class AddToCart
{
    public string $sku = '';
    public int $quantity = 1;
    public ?string $customerGroup = null;

    #[AllowedQuantity]
    public function requested(): RequestedQuantity
    {
        return new RequestedQuantity($this->sku, $this->quantity, $this->customerGroup);
    }
}
```

| Erreur | Code | Clé du message dans le domaine `validators` |
|---|---|---|
| Sous le minimum | `AllowedQuantity::BELOW_MINIMUM_ERROR` | `phil_commerce_wholesale.quantity.below_minimum` |
| Au-dessus du maximum | `AllowedQuantity::ABOVE_MAXIMUM_ERROR` | `phil_commerce_wholesale.quantity.above_maximum` |
| Entre deux tranches | `AllowedQuantity::OFF_INCREMENT_ERROR` | `phil_commerce_wholesale.quantity.off_increment` |

Le bundle traduit les clés en anglais et en français, dans `translations/validators.en.php` et `validators.fr.php` ;
les paramètres `{{ sku }}`, `{{ quantity }}`, `{{ minimum }}`, `{{ maximum }}`, `{{ increment }}` et `{{ allowed }}` —
la quantité permise la plus proche — laissent une application écrire ses propres messages, par les options
`belowMinimumMessage`, `aboveMaximumMessage` et `offIncrementMessage` de la contrainte. Le traducteur de
FrameworkBundle doit être activé pour que les messages soient traduits.

## Dans le panier

`QuantityGuard` est un résolveur de prix unitaire du noyau, interrogé avant les carnets de prix du catalogue et
`GroupPrices`. Le noyau interroge ses résolveurs pour la quantité entière d'une ligne — ce que la ligne contient déjà
plus ce qui est ajouté — et pour le groupe de clients de la commande, avant de changer la commande. Le gardien ne
donne aucun prix : il laisse passer une quantité permise vers les résolveurs qui le suivent, et en refuse une autre
par `QuantityRefused`, dont les `messages` sont ceux du validateur, traduits dans la langue de la requête. La commande
reste telle qu'elle était.

- Seuls les produits sont vérifiés : leur référence nomme le *SKU*. Les offres et les autres articles vendables sont
  laissés tels quels.
- Une ligne configurée — le même *SKU* avec d'autres valeurs d'options — est vérifiée sur sa propre quantité.
- Changer le groupe de la commande vérifie de nouveau chaque ligne pour le nouveau groupe.

## Stockage

Avec DoctrineBundle, une règle est une rangée de `phil_commerce_wholesale_quantity_rule` — `sku` et `customer_group`
(clé primaire ; un groupe vide pour tous les groupes), `minimum`, `maximum` (nullable), `increment`.
