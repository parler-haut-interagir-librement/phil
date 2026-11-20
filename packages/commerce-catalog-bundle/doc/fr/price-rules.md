<!-- phil-dev source-sha256=1756fcccd37624697fbe534b12de9cbc7eebeb9a18fafab8d73a50586b2ffdea -->
# Règles de prix

Un produit est vendu à son prix, ou selon l'une de deux règles : l'acheteur choisit le prix, ou d'autres acheteurs
financent des copies pour des gens qui ne peuvent pas payer. Un rabais pour un acheteur qui possède déjà un produit
apparenté est une promotion de `phil/commerce-promotions-bundle`, par sa condition sur les produits possédés.

## Payez ce que vous voulez

```php
$zine->payWhatYouWant(suggested: Money::of(800, Currency::of('CAD')), maximum: Money::of(5_000, Currency::of('CAD')));

$modifier->add(
    $order,
    PriceRuleSellables::reference($zine),                                  // pay_what_you_want:ZINE
    1,
    $context,
    PriceRuleSellables::configuration(Money::of(1_200, Currency::of('CAD'))), // the option price: 1200
);                                                                         // a line at 12.00
```

Le prix de l'acheteur est une option de la ligne, l'option de montant du noyau (`OptionKind::Amount`).
`PriceRuleSellables` répond pour le noyau à la référence `pay_what_you_want:<sku>` — le nom du produit dans la locale de
l'acheteur, un prix unitaire de zéro, la catégorie de taxe du produit — et déclare, comme `SellableOptions` du noyau,
une option obligatoire, `price`, dont l'`AmountRange` va du prix de base jusqu'au maximum. Le noyau vérifie la valeur,
la décrit sur la ligne (`12.00 CAD`) et la facture comme supplément de l'option, taxée avec la ligne dans la catégorie
de taxe du produit. Deux prix font deux lignes, leurs configurations différant ; le même prix de nouveau s'ajoute à sa
ligne.

| Membre | Règle |
|---|---|
| `Product::payWhatYouWant(?suggested, ?maximum)` | Le prix suggéré n'est pas sous le prix de base, et le maximum n'est sous ni le prix suggéré ni le prix de base ; les deux dans la devise du produit |
| `Product::fixPrice()` | Vend de nouveau le produit à son prix |
| `Product::isPayWhatYouWant()`, `suggestedPrice()`, `maximumPrice()` | Lisent la règle |
| `Product::chosenPrices(?defaultMaximum)` | L'`AmountRange` dans laquelle l'acheteur choisit, du prix de base jusqu'au maximum du produit ou au maximum par défaut ; `null` pour un prix fixe, sans maximum, ou avec un maximum par défaut dans une autre devise ou sous le prix suggéré — le prix de base quand aucun n'est suggéré |
| `Product::reprice()` | Le prix de base reste sous le prix suggéré et le maximum |
| `PriceRuleSellables::reference(product)` | `pay_what_you_want:<sku>` |
| `PriceRuleSellables::configuration(price)` | La configuration de la ligne : l'option `price`, en unités mineures écrites en chiffres |
| `PriceRuleSellables::chosenPriceOf(line)` | Ce que coûte une unité d'une ligne à prix choisi avant ses promotions ; `null` pour une autre ligne |

- **Le maximum est obligatoire.** Un produit qui n'en a pas de propre prend celui du site,
  `pay_what_you_want.default_maximum` ([Configuration](configuration.md)) ; sans l'un ni l'autre, le produit n'est pas
  vendu à un prix choisi — le noyau ne trouve rien pour sa référence —, si bien qu'un acheteur n'atteint jamais le plus
  grand entier.
- **La valeur est en unités mineures**, des chiffres sans signe, sans point décimal et sans zéro au début. Un prix sous
  le prix de base, au-dessus du maximum, qui n'est pas écrit comme un montant, ou laissé vide, est refusé par
  `InvalidConfiguration` du noyau, qui nomme l'option `price`, avant que la commande ne change.
- **Le libellé de l'option** est `Your price`, la clé `phil_commerce_catalog.chosen_price` ; une page montre d'abord le
  prix suggéré, lu dans `suggestedPrice()`.
- **Refusés** : un produit qui ne laisse pas l'acheteur choisir, un produit retiré et une famille ne sont jamais
  vendus. Une ligne dont le produit cesse de laisser l'acheteur choisir, ou perd son maximum, n'est jamais laissée à
  zéro : relire ses options — le noyau le fait à chaque recalcul — lance `CatalogRefused` jusqu'à ce que la ligne soit
  retirée. Une commande dans une autre devise que celle du produit est refusée de la même façon.
- Les canaux qui vendent le produit vendent ses prix choisis, par `ProductAvailability`. Les grilles de prix ne donnent
  de prix qu'au type `product` : un prix choisi est le prix.

## Copies communautaires

```php
$zine->offerCommunityCopies(Money::of(500, Currency::of('CAD')));

$modifier->add($order, SellableReference::of('community_copy', 'ZINE'), 2, $context);   // funds two copies

$entityManager->wrapInTransaction(fn () => $copies->claim($zine, $member->id(), $clock->now()));
```

Une copie communautaire est financée par d'autres acheteurs et réclamée par quelqu'un qui ne peut pas payer.
`CommunityCopies` écoute `OrderPurchased` du noyau et lit la commande payée en entier, avant ses promotions et ses
taxes :

- chaque ligne `community_copy:<sku>` donne ce qu'elle coûte, le prix d'une copie fois ses unités ;
- chaque ligne `pay_what_you_want:<sku>` donne ce que son acheteur a payé au-dessus du prix suggéré, ou au-dessus du
  prix de base quand aucun n'est suggéré, fois ses unités — le prix choisi étant son option `price`.

| Membre | Règle |
|---|---|
| `Product::offerCommunityCopies(?copyPrice)` | Plus que zéro, dans la devise du produit ; `null` n'en offre aucune et garde ce qui a été financé et réclamé |
| `CommunityCopies::available(product)` | Une copie par prix de copie financé, moins les copies réclamées ; jamais sous zéro |
| `CommunityCopies::funded(product)` | Ce qui a financé ses copies ; `null` quand il n'en offre aucune |
| `CommunityCopies::claim(product, claimant, at)` | Une copie par demandeur et par produit, tant qu'il en reste une ; refusée par `CatalogRefused` autrement |
| `CommunityCopies::fund(order, at)` | Enregistre ce qu'une commande payée a donné ; les *SKU* qu'elle a financés |

- **Une commande finance une fois.** Son financement est une rangée par produit et par commande : l'événement livré
  deux fois, ou un *worker* qui réessaie, n'ajoute rien.
- **Une réclamation s'exécute dans une transaction.** Elle verrouille d'abord la rangée du produit, si bien que deux
  réclamations de la dernière copie s'exécutent l'une après l'autre, et la seconde est refusée.
- **Le demandeur est un identifiant stable**, de 1 à 64 caractères ASCII visibles — jamais un nom ni une adresse.
  L'application livre la copie : un téléchargement, une copie mise de côté au comptoir.
- **Une commande dans une autre devise que celle du produit ne finance rien.**

La ligne d'une copie communautaire est décrite par `Community copy of <name>`, la clé
`phil_commerce_catalog.community_copy` du domaine `phil_commerce_catalog`, que le module traduit en anglais et en
français ; une application la remplace dans ses propres traductions.

## Stockage

Avec DoctrineBundle, les règles sont des colonnes de `phil_commerce_catalog_product` — voyez [Produits](products.md) —,
et les copies communautaires occupent deux tables.

| Table | Clé primaire | Colonnes |
|---|---|---|
| `phil_commerce_catalog_community_copy_funding` | `sku`, `order_number`, `VARCHAR(64)` chacun | `amount` `BIGINT`, en unités mineures du produit ; `funded_at` `DATETIME`, en UTC |
| `phil_commerce_catalog_community_copy_claim` | `sku`, `claimant`, `VARCHAR(64)` chacun | `claimed_at` `DATETIME`, en UTC |

`DoctrineCommunityCopyLedger` additionne le financement et compte les réclamations en SQL, et verrouille le produit avec
`PESSIMISTIC_WRITE`.
