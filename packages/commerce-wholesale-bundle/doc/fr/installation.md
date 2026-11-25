<!-- phil-dev source-sha256=c92229e2ade5a5f53385acb3edb36341145f0818c01d72ae6ea6ec0825683775 -->
# Installation

## Prérequis

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute | Compte les caractères du nom d'acheteur d'un compte de crédit |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau sur lequel le module se branche : ses commandes et `UnitPriceResolver` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Les commandes minimales et les prix de groupe |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance des groupes et des appartenances, et l'adaptateur de dépôt |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` |
| `symfony/config` | ^8.2 | Exigé par `AbstractBundle` quand le module est installé seul |
| `symfony/uid` | ^8.2 | Les identifiants de créances |
| `symfony/validator` | ^8.2 | La contrainte `AllowedQuantity`, vérifiée par le validateur dans les formulaires et le panier |

Facultatif : `phil/commerce-logistics-bundle` 0.3 ou plus récent, dont l'acheteur à crédit reçoit alors les
expéditions avant de les payer ([Ventes à crédit](credit.md#expédier-avant-le-paiement)).

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-wholesale-bundle
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
    Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceWholesaleBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

Le module n'a aucune clé de configuration. Ses services lisent un dépôt : avec DoctrineBundle, le module les enregistre
avec son adaptateur Doctrine — générez une migration pour ses cinq tables — ; sans lui, l'application les déclare avec
son propre adaptateur. Les règles de quantité ont besoin du validateur de FrameworkBundle, et de son traducteur pour
leurs messages dans la langue de l'acheteur.
