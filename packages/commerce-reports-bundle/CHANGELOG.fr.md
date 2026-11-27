<!-- phil-dev source-sha256=0b1586abce3061b08c41f365a0743d6193881fef2325460a6d59c4be95048a9b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/commerce-reports-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-24

### Ajouté

- Les ports `SoldLines`, `ReturnedLines`, `StockFigures` et `StockedSkus`, et les objets-valeurs `SoldLine`,
  `ReturnedLine`, `StockFigure` et `StockedSku`, chacun borné, avec `InvalidFigure`.
- `SellThrough::ppm()` : les unités vendues × 1 000 000 / (stock de départ + unités reçues), une moitié arrondie vers le
  haut, nul sans rien à vendre ; `Dormancy` : le stock dormant après un nombre de jours sans vente, 90 par défaut,
  compté d'après l'horloge.
- `SalesLedger` et `SalesTotals` : les lignes vendues et retournées dans une période, totalisées par produit, catégorie,
  canal, emplacement ou caissier et par devise avec `phil/money`, 50 000 totaux au plus.
- Les rapports `commerce_sales`, `commerce_sell_through` et `commerce_dead_stock`, étiquetés parmi les rapports de
  `phil/reporting-bundle`, avec les paramètres `by` et `days`.
- `PosSoldLines`, les lignes vendues au point de vente, lues des sessions de caisse fermées dans la période et des
  commandes du noyau du commerce ; `LogisticsStockFigures`, les chiffres de stock lus du journal de stock du module de
  logistique.
- Les adaptateurs en mémoire des quatre ports, et `PortsPass`, qui donne un alias à chaque port que l'application laisse
  tel quel.
- L'option `dead_stock_days`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- La recette Flex, dans `recipe/0.1`.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
