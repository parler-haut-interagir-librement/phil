<!-- phil-dev source-sha256=dd670f6424ec5af1ec4cb3cf87baa36a025f61aba587872e4b929fdaa603fb0b -->
# Référence

Chaque type public du paquet, avec son rôle.

## Services

| Type | Rôle |
|---|---|
| `Bundles` | Les lots par SKU : devis, stock, coût, marge, et la ligne que fige une vente. |
| `BundlePricing` | Établit le devis d'un lot d'après les prix de ses composants et partage son prix entre eux. |
| `BundleAvailability` | Les lots qui peuvent être vendus, fixés par le composant le plus rare. |
| `ComposedCost` | Le coût d'un lot composé des coûts de ses composants, et sa marge. |
| `Memory\InMemoryBundles` | Les lots dans un tableau. |

## Valeurs

| Type | Rôle |
|---|---|
| `ProductBundle` | Un SKU sur 1 à 50 composants, un genre, une devise et une politique de prix ; stocké et relu. |
| `BundleComponent` | Le SKU d'un composant et les unités qu'un lot en contient. |
| `BundleKind` | `Assembled` ou `Collection` ; `taxedAsOne()`. |
| `PricePolicy` | Un prix fixe, ou un rabais sur les composants avec son `\RoundingMode`. |
| `BundleQuote` | La valeur des composants, le prix, les parts ; `savings()`. |
| `QuotedComponent` | Un composant d'un devis : prix unitaire, valeur, part. |
| `BundleStock` | Les lots qui peuvent être vendus et le composant qui fixe la limite. |
| `BundleCost` | Le coût composé d'un lot, ou les composants dont le coût est inconnu. |
| `ComponentCost` | Le coût unitaire d'un composant et son coût pour un lot. |
| `BundleMargin` | Prix, coût, marge et taux de marge. |
| `SoldBundle` | Une vente figée sur sa ligne : prix, parts, coûts, quantités ; prélèvements et retours complets. |
| `SoldComponent` | Un composant tel qu'il a été vendu. |
| `StockDraw` | Les unités d'un composant à sortir ou à remettre. |
| `BundleRefund` | Les lots entiers retournés : montant, parts, unités à remettre, la ligne après. |
| `Cooperative\CooperativeBundle` | Un lot partagé entre vendeurs : poids, approbations, verrou, organisme de bienfaisance, parts. |
| `Cooperative\CooperativeShare` | La part d'un participant dans un revenu, ou celle de l'organisme de bienfaisance. |

## Ports

| Type | Rôle |
|---|---|
| `Contract\BundleRepository` | Garde les lots. |
| `Contract\ComponentPrices` | Le prix unitaire d'un composant dans une devise. |
| `Contract\ComponentStock` | Les unités disponibles d'un composant. |
| `Contract\ComponentCosts` | Le coût unitaire d'un composant maintenant. |

## Exceptions

| Type | Lancée quand |
|---|---|
| `Exception\ProductBundlesException` | L'interface de chaque exception du paquet. |
| `Exception\InvalidBundle` | Un SKU, une quantité, un réglage ou une ligne stockée est hors des bornes. |
| `Exception\UnknownBundle` | Aucun lot n'a ce SKU. |
| `Exception\PriceUnknown` | Un composant n'a pas de prix dans la devise du lot. |
| `Exception\SaleRefused` | Les composants ne peuvent pas remplir une vente. |
| `Exception\ReturnRefused` | Un retour demande moins d'un lot, ou plus qu'il n'en reste. |
| `Exception\CooperativeRefused` | Un lot coopératif refuse un changement de son état. |
