<!-- phil-dev source-sha256=b5be7d34a261eb7523803ec4eadf5666c90eb9da0150f888877eefcde82ef465 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute version | La longueur des libellés, en caractères |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau auquel le module se branche : `OrderProcessor`, `Order`, `Adjustment` |
| `phil/commerce-catalog-bundle` | ^0.1@dev | Les produits que les promotions réduisent, sous le type de référence `product` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/doctrine-behaviours` | ^0.2@dev | Les traductions des libellés |
| `phil/locale-dimensions` | ^0.1@dev | Les locales servies et leurs chaînes de repli |
| `phil/money` | ^0.1@dev | Les montants de rabais, les prix de lots et les minimums |
| `doctrine/orm` | ^3.7 | Les attributs de mappage et l'adaptateur du dépôt |
| `psr/clock` | ^1.0 | Le moment auquel les promotions sont vérifiées ; FrameworkBundle fournit l'horloge avec `symfony/clock` |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle est un `AbstractBundle` avec un arbre de configuration |
| `symfony/translation-contracts` | ^3.6 | La locale de la requête, lue depuis le traducteur |

`phil/commerce-logistics-bundle` est suggéré : il calcule les frais de livraison qu'une livraison gratuite annule.

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-promotions-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez le noyau, le catalogue et le module ; l'ordre des lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle::class => ['all' => true],
];
```

Enregistré sans le noyau ou le catalogue, le module fait échouer la construction du conteneur :

```text
PhilCommercePromotionsBundle is a module of the core of commerce on its catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.
```

Avec DoctrineBundle, générez une migration pour ses tables `phil_commerce_promotion`,
`phil_commerce_promotion_translation`, `phil_commerce_promotion_code`, `phil_commerce_promotion_redemption` et
`phil_commerce_promotion_customer_use`. Enregistrez `TranslationListener` de `phil/doctrine-behaviours` — le
`phil/doctrine-behaviours-bundle` le fait — pour que les traductions gardent une rangée par promotion et par locale.

Chaque service du module lit le dépôt : avec DoctrineBundle, le module les enregistre avec son adaptateur Doctrine ;
sans lui, il n'enregistre rien, et l'application déclare les services avec son propre adaptateur.
