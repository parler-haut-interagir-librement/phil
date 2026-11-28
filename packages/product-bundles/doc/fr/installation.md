<!-- phil-dev source-sha256=9bd6444a1953dcc6b192d80f43af0b3860e3cf58eb4a3d24119f292680b12b78 -->
# Installation

```sh
composer require phil/product-bundles
```

Le paquet exige PHP 8.5 ou une version plus récente. Il requiert phil/money, pour les montants dans leur devise, et
phil/pricing, pour le rabais, la marge et leurs taux en parties par million.

## Ce que fournit l'application

| Quoi | Pourquoi |
|---|---|
| Un magasin pour les lots, implémentant `Contract\BundleRepository` | Garde ce que donne `ProductBundle::toArray()`, par SKU, et trouve les lots qui contiennent un composant. |
| Les prix des composants, implémentant `Contract\ComponentPrices` | Le prix unitaire d'un SKU dans une devise, tel que le catalogue le vend seul. |
| Le stock des composants, implémentant `Contract\ComponentStock` | Les unités d'un SKU qui peuvent être vendues maintenant. |
| Les coûts des composants, implémentant `Contract\ComponentCosts` | Le coût unitaire d'un SKU maintenant, tel que l'évaluation des stocks le dit. |
| Une colonne sur la ligne de commande | Garde ce que donne `SoldBundle::toArray()`, pour le retour et les rapports. |
| Un magasin pour les lots coopératifs, quand elle en vend | Garde ce que donne `CooperativeBundle::toArray()`. |

`Memory\InMemoryBundles` sert les tests et un processus unique. Les [points d'extension](extension-points.md) montrent
des adaptateurs sur phil/commerce-catalog-bundle et phil/commerce-logistics-bundle.

## Le câblage

Les services reçoivent leurs ports dans leurs constructeurs et ne gardent aucun état entre les appels :

```php
$bundles = new Bundles(
    $bundleRepository,
    new BundlePricing($componentPrices),
    new BundleAvailability($componentStock),
    new ComposedCost($componentCosts),
);
```

Dans une application Symfony, déclarez-les comme services avec l'autowiring ; ils n'ont besoin d'aucune
réinitialisation entre les requêtes.
