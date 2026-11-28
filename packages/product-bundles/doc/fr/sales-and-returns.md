<!-- phil-dev source-sha256=dcd3674bc8167fc12bf7d4a89b0ed4f19a3891519085646db0d0f84a317e4eba -->
# Ventes et retours

## Vendre

`Bundles::sell($sku, $quantity)` vérifie le stock, établit le devis du lot, compose son coût et fige les trois dans un
`SoldBundle`, de 1 à 1 000 lots. Quand les composants ne peuvent pas remplir la vente, elle lance `SaleRefused`, en
nommant le composant qui fixe la limite ; `backorder: true` vend quand même, pour une boutique qui vend en
réapprovisionnement différé.

`SoldBundle::of()` fige un devis propre à l'application, avec ou sans coût.

## La ligne figée

| Champ ou méthode | Contenu |
|---|---|
| `unitPrice`, `total()` | Le prix d'un lot, et celui de la quantité vendue. |
| `components` | Un `SoldComponent` chacun : unités par lot, part du prix d'un lot, coût unitaire quand il est connu. |
| `shares()` | Le total réparti entre les composants, s'additionnant à lui : ce sur quoi une collection est taxée. |
| `unitCost()` | Ce qu'un lot a coûté à la vente ; nul quand le coût d'un composant était inconnu. |
| `draws()` | Un `StockDraw` par composant : les unités que la vente réserve ou sort. |
| `quantity`, `returned`, `returnable()` | Vendu, retourné et reste à retourner. |

La ligne est stockée avec la commande : `toArray()` ne donne que des scalaires, chaque coût unitaire avec sa propre
devise, et `fromArray()` la relit, en refusant une ligne dont les parts ne s'additionnent pas au prix unitaire.

## Taxes

Un lot assemblé est taxé sur `total()` par la catégorie de taxe du lot lui-même. Une collection est taxée sur chacune
des `shares()` par la catégorie de son composant, de sorte qu'un livre imprimé et son PDF sont chacun taxés à leur
propre taux.

## Retourner

`SoldBundle::refund($bundles)` retourne des lots entiers, jamais un composant seul :

| Champ | Contenu |
|---|---|
| `amount` | Le prix unitaire multiplié par les lots retournés : ce qui a été payé pour eux. |
| `shares` | Le montant réparti entre les composants, pour les taxes d'une collection. |
| `restock` | Un `StockDraw` par composant : les unités à remettre. |
| `after` | Le lot vendu, ces lots comptés comme retournés ; stockez-le par-dessus la ligne. |

Moins d'un lot, ou plus que ce qui reste à retourner, lance `ReturnRefused`. Les unités reviennent au coût que
l'évaluation des stocks a enregistré à leur sortie ; la bibliothèque remet les unités, la logistique les évalue.
