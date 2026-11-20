<!-- phil-dev source-sha256=ab2d40361b309c6a50a31d964c8adf58654e5a53b32ab50b86474d38380f56f6 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses |
| `ext-sodium` | Le nom aléatoire du fichier temporaire dans lequel une maquette est écrite |
| `phil/commerce-bundle` | Le noyau : les commandes, leurs lignes configurées et le port `SellableOptions` |
| `phil/commerce-catalog-bundle` | Le catalogue dans lequel les produits sont générés |
| `phil/commerce-product-template` | Les gabarits, les motifs et le planificateur |
| `phil/print-compositor` | Les maquettes, dessinées avec GD |
| `phil/ddd-bundle`, `phil/money` | Les erreurs du domaine et les montants |
| Doctrine ORM 3.7, DoctrineBundle | Le stockage des gabarits, des motifs et des groupes |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-product-template-bundle
```

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez les trois bundles ; le module refuse de se charger sans le noyau et le catalogue :

```php
// config/bundles.php
return [
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle::class => ['all' => true],
];
```

Avec DoctrineBundle, le module déclare la correspondance de ses quatre entités ; créez leurs tables avec les migrations
de l'application ([Stockage](storage.md)). Sans DoctrineBundle, seul le planificateur est enregistré, et l'application
déclare les autres services avec ses propres dépôts ([Points d'extension](extension-points.md)).
