<!-- phil-dev source-sha256=d6f938ad023a3150575e5bd9e00259882ecce6a7412c49ccc55196423b6466a7 -->
# Les rapports

Chaque rapport est un `ReportDefinition` du bundle de rapports : une requête donne sa période, début inclus et fin
exclue, et ses paramètres.

```php
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;

$queue->queue(new ReportJob('commerce_sales', 'csv', new ReportRequest($period, ['by' => 'category']), 'mail', ['manager@example.com']));
```

## Ventes brutes et nettes : `commerce_sales`

Une ligne par valeur de la dimension et par devise, triée par valeur, puis par devise.

| Paramètre | Valeurs | Valeur par défaut |
|---|---|---|
| `by` | `product`, `category`, `channel`, `location`, `cashier` | `product` |

| Colonne | Ce qu'elle contient |
|---|---|
| UGS, ou la dimension | La valeur ; vide pour les lignes qui n'en ont pas, une vente sans caissier par exemple. |
| Produit, ou Nom | Le nom du produit, ou de nouveau la valeur. |
| Devise | Le code ISO 4217 des montants de la ligne. |
| Unités vendues | Les unités des lignes vendues pendant la période. |
| Unités retournées | Les unités des lignes retournées pendant la période. |
| Ventes brutes | Les prix des lignes multipliés par leurs quantités, avant les remises, sans les taxes ni les pourboires. |
| Remises | Les remises des lignes vendues, en montant positif. |
| Retours | Les montants remboursés pour les lignes retournées, sans les taxes. |
| Ventes nettes | `gross − discounts − returns`. |

Les montants sont des décimaux à la précision de leur devise, calculés avec `phil/money` en unités mineures : jamais un
nombre à virgule flottante, jamais arrondis, et jamais additionnés entre devises — un produit vendu en dollars et en
euros a deux lignes.

Un retour compte dans la période où il a été reçu, quelle que soit la période de sa vente : une période qui rembourse
plus qu'elle ne vend a des ventes nettes négatives, et un produit seulement retourné pendant la période a une ligne
sans unité vendue.

## Écoulement : `commerce_sell_through`

| Paramètre | Valeurs | Valeur par défaut |
|---|---|---|
| `by` | `sku`, `category` | `sku` |

Les lignes sont les UGS que `StockedSkus` liste et celles vendues pendant la période, triées par UGS ; par catégorie,
leurs sommes, triées par catégorie, les UGS sans catégorie sous une catégorie vide.

```text
sell-through (ppm) = units sold × 1 000 000 / (starting stock + units received)
```

- le résultat est arrondi à la partie par million entière la plus proche, une moitié arrondie vers le haut : 1 vendue
  sur 3 donne 333 333, 2 sur 3 donnent 666 667, 1 sur 2 000 000 donne 1 ;
- sans rien à vendre — un stock de départ et des unités reçues dont la somme est nulle ou négative — l'écoulement est
  vide, jamais une division par zéro ;
- une période qui a vendu plus qu'elle n'avait, à cause de commandes en souffrance, donne plus de 1 000 000 ;
- le pourcentage est le nombre de parties par million divisé par 10 000 : 250 000 ppm font 25 %.

| Colonne | Ce qu'elle contient |
|---|---|
| UGS, ou Catégorie | L'UGS, ou la catégorie. |
| Produit, ou UGS | Le nom du produit, ou le nombre d'UGS de la catégorie. |
| Stock de départ | Les unités en main au début de la période. |
| Unités reçues | Les unités reçues pendant la période. |
| Unités vendues | Les unités des lignes vendues pendant la période, retours non déduits. |
| Écoulement (ppm) | La formule ci-dessus ; vide sans rien à vendre. |
| Écoulement (%) | La même, en pourcentage. |

## Stock dormant : `commerce_dead_stock`

Chaque UGS que `StockedSkus` liste, dans son ordre, qui a des unités disponibles et ne s'est pas vendue depuis `days`
jours, ou ne s'est jamais vendue.

| Paramètre | Valeurs | Valeur par défaut |
|---|---|---|
| `days` | un nombre entier de 1 à 3650, ou le même écrit en texte | `dead_stock_days`, 90 |

- l'âge est compté depuis maintenant, tel que `Psr\Clock\ClockInterface` le donne, et non depuis la fin de la période ;
- une dernière vente d'il y a exactement `days` jours est du stock dormant ; une vente d'une seconde plus récente ne
  l'est pas : les moments se comparent par `<` et `>`, jamais pour l'égalité ;
- une UGS sans unité disponible n'est jamais du stock dormant, quelle que soit sa dernière vente.

| Colonne | Ce qu'elle contient |
|---|---|
| UGS | L'UGS. |
| Produit | Son nom. |
| Catégorie | Sa catégorie, vide sans catégorie. |
| Unités disponibles | Les unités disponibles maintenant. |
| Dernière vente | Le jour de sa dernière vente, vide quand elle ne s'est jamais vendue. |
| Jours sans vente | Les jours entiers de la dernière vente à maintenant, vide quand elle ne s'est jamais vendue. |

## Bornes

Une période donne au plus 50 000 totaux de ventes et lit au plus 100 000 UGS pour son écoulement ; au-delà, le rapport
s'arrête avec `ReportTooLarge` du bundle de rapports. Un paramètre hors de ses valeurs l'arrête avec
`InvalidReportJob`. Les rapports gardent leurs totaux en mémoire, jamais les lignes, que les ports fournissent une à
la fois.
