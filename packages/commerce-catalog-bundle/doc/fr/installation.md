<!-- phil-dev source-sha256=38a3fb87b4954179a0386c58d2f066a3836da234be483f8bb3209cd0ab1aa376 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute version | La longueur des noms et des valeurs d'attributs, en caractères |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau auquel le module se branche : `SellableResolver`, `UnitPriceResolver`, `SellableAvailability`, `OrderPurchased` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/doctrine-behaviours` | ^0.2@dev | Les traductions des noms des produits |
| `phil/locale-dimensions` | ^0.1@dev | Les locales servies et leurs chaînes de repli |
| `phil/money` | ^0.1@dev | Les prix de base, les prix des grilles et les règles de prix |
| `doctrine/orm` | ^3.7 | Les attributs de mappage des produits, des grilles de prix et des copies communautaires, et les adaptateurs des dépôts |
| `doctrine/dbal` | ^4.5 | `PriceBookOrderMigration`, qui modifie la table des grilles de prix, et `DbalProductListing` |
| `psr/clock` | ^1.0 | Le moment auquel un flux lit les grilles de prix |
| `psr/log` | ^3.0 | Les produits qu'un flux omet, et les flux publiés |
| `symfony/polyfill-php86` | ^1.37 | `\SortDirection` sur PHP 8.5, pour les dépôts et les listes |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle est un `AbstractBundle` avec un arbre de configuration ; `BeforeAfterSorter` classe les grilles de prix |
| `symfony/translation-contracts` | ^3.6 | La locale de la requête, lue depuis le traducteur, et la description d'une copie communautaire |

`phil/taxonomy-bundle` est suggéré : avec sa taxonomie enregistrée, les produits sont classés dans ses catégories et
ses étiquettes. `phil/product-feeds` est suggéré : avec lui, le catalogue est publié comme fichiers de flux ;
`symfony/messenger` les publie par un message, `symfony/console` donne leur commande, et `symfony/http-foundation` le
contrôleur de la route qui sert leurs fichiers ([Flux de produits](product-feeds.md#servir)).
`phil/commerce-promotions-bundle` est le module des promotions sur ces produits.

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; celle du module ajoute aussi
`config/routes/phil_commerce_catalog.yaml`, les routes des flux de produits. Sans Flex :

Enregistrez le noyau et le module ; l'ordre des deux lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceCatalogBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Les clés de configuration du module sont les locales dans lesquelles les noms des produits sont traduits et les flux de
produits : voyez [Configuration](configuration.md). Avec DoctrineBundle, générez une migration pour ses tables
`phil_commerce_catalog_product` — ses colonnes `visibility`, `orderable` et `price_shown` comprises —,
`phil_commerce_catalog_product_translation`, `phil_commerce_catalog_price_book`,
`phil_commerce_catalog_community_copy_funding` et `phil_commerce_catalog_community_copy_claim` — une table de grilles
de prix classées par numéro passe par `PriceBookOrderMigration`, voyez [Grilles de prix](price-books.md) —, et
enregistrez `TranslationListener` de `phil/doctrine-behaviours` — le `phil/doctrine-behaviours-bundle` le fait — pour
que les traductions gardent une rangée par produit et par locale.

Chaque service du module lit un dépôt. Avec DoctrineBundle, le module les enregistre avec ses adaptateurs Doctrine ;
sans lui, il n'enregistre rien, et l'application déclare les services avec ses propres adaptateurs.
