<!-- phil-dev source-sha256=03a4b55ff1288ad74be238f2d56c51ff3ba42cc46e1f248a9dad4c60fda213e1 -->
# Groupes de clients

Les groupes de clients sont le modèle de la vente en gros : un groupe vend selon ses propres conditions, et chaque
acheteur appartient à un groupe au plus. L'acheteur est nommé comme l'application le nomme — un compte, ou le locataire
d'une application multilocataire, une boutique qui achète pour ses propres clients.

```php
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;

$groups->addGroup(CustomerGroup::of('wholesale', 'Grossistes', Currency::of('CAD'),
    discount: 250_000, minimumOrder: Money::of(50_000, Currency::of('CAD'))));

$customerGroups->join($tenantId, 'wholesale');

// When the buyer starts an order
$modifier->changeAudience($order, 'web', $customerGroups->groupOf($tenantId), $context);

// Before placing it
if ($customerGroups->shortOfMinimum($order)->isPositive()) { /* not yet */ }
```

| Membre de `CustomerGroup` | Règle |
|---|---|
| `code` | De 1 à 64 caractères ASCII visibles, comme la commande du noyau nomme son groupe de clients |
| `name()`, `rename()` | Non vide |
| `discount()`, `changeTerms()` | De 0 à 999 999 parties par million retranchées des prix de base : `250_000` vaut 25 % |
| `minimumOrder()` | Zéro ou plus, dans la devise du groupe : ce que les lignes de la commande doivent atteindre |
| `priceFrom(Money)` | Le prix de base moins la remise, arrondi à l'unité mineure la plus proche |
| `withdraw()`, `restore()` | Un groupe retiré ne donne ni prix ni minimum, et n'est le groupe de personne |

| Méthode de `CustomerGroups` | Effet |
|---|---|
| `join(buyer, groupCode)` | Place l'acheteur dans le groupe, ou l'y déplace ; un groupe inconnu lève `WholesaleRefused` |
| `leave(buyer)` | Retire l'acheteur de son groupe |
| `groupOf(buyer)` | Le groupe de l'acheteur tant qu'il est actif ; `null` sinon |
| `shortOfMinimum(order)` | Ce qu'il manque encore aux lignes de la commande pour atteindre le minimum de son groupe ; zéro une fois atteint, ou sans groupe |

## Le prix que prend un produit

`GroupPrices` est un résolveur de prix unitaire du noyau, étiqueté après `PriceBookPrices` du catalogue : il est
interrogé après les carnets de prix. Ainsi, pour le groupe de clients de la commande :

1. un carnet de prix qui nomme le groupe — sa propre liste de prix — donne le prix, quand il fixe celui du produit ;
2. sinon, la remise du groupe est retranchée du prix de base du produit ;
3. sinon, pour un groupe sans remise, le prix de base.

Seuls les produits sont tarifés par le groupe ; les offres et les autres articles vendables gardent leurs propres prix.

## Stockage

Avec DoctrineBundle, un groupe est une ligne de `phil_commerce_wholesale_group` — `code` (clé primaire), `name`,
`currency`, `discount`, `minimum_order`, `active` —, et une appartenance une ligne de
`phil_commerce_wholesale_membership` — `buyer` (clé primaire), `customer_group` (indexée).
