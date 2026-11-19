<!-- phil-dev source-sha256=1adeba2550f3ed5b44d54cbde1112e02262b28718db2dc4a77703c0149d4bf92 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau sur lequel le module se branche : ses commandes, `OrderModifier` et `SellableResolver` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Le prix d'une offre |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance de `Offer`, `Subscription`, `Gift` et `Seat`, et les adaptateurs des dépôts |
| `symfony/uid` | ^8.2 | L'UUID ordonné dans le temps qui nomme un abonnement et un cadeau |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` |
| `symfony/config` | ^8.2 | L'arbre de configuration |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-subscription-bundle
```

## Enregistrer

Avec Symfony Flex, la recette de chaque paquet enregistre son bundle ; les valeurs par défaut du module n'exigent aucun fichier de configuration.
Sans Flex :

Enregistrez le noyau et le module ; l'ordre des deux lignes n'a pas d'importance.

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceSubscriptionBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Les clés du module sont décrites dans [configuration.md](configuration.md). Avec DoctrineBundle, générez une
migration pour ses quatre tables.

Chaque service du module lit un dépôt : avec DoctrineBundle, le module les enregistre avec ses adaptateurs Doctrine ;
sans lui, il n'enregistre rien, et l'application déclare les services avec ses propres adaptateurs.
