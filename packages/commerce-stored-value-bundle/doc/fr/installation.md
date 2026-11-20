<!-- phil-dev source-sha256=7a40060ccbc6c98a36aeb72048a4d7dde1a5987646c446346a36ddfc035fd0c2 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le module déclare et sur lequel il est testé |
| `ext-mbstring` | toute | Compte les caractères des détenteurs et des notes |
| `phil/commerce-bundle` | ^0.2@dev ou ^0.3@dev | Le noyau sur lequel le module se branche : ses commandes, ses paiements, `SellableResolver` et `PaymentGateway` |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError` |
| `phil/money` | ^0.1@dev | Les soldes et les mouvements |
| `phil/paged-media` | ^0.1@dev | La carte-cadeau imprimée |
| `doctrine/orm` | ^3.7 | Les attributs de correspondance des instruments et des mouvements, et l'adaptateur du dépôt |
| `psr/clock` | ^1.0 | Le moment où un remboursement rend la valeur |
| `symfony/uid` | ^8.2 | Les identifiants des instruments et des mouvements |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et son arbre de configuration |

## Installer

```sh
composer require phil/commerce-bundle phil/commerce-stored-value-bundle
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
    Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle::class => ['all' => true],
];
```

Enregistré sans le noyau, le module fait échouer la construction du conteneur :

```text
PhilCommerceStoredValueBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.
```

## Configurer

Chaque clé est facultative.

```yaml
# config/packages/phil_commerce_stored_value.yaml
phil_commerce_stored_value:
    gift_card:
        label: 'Gift card'        # how a gift card line reads, before its value
        tax_category: exempt      # selling a gift card is, in most places, not taxed
        max_value: 100000         # the highest value sold, in minor units
```

Donnez au noyau un taux de taxe pour cette catégorie, à zéro là où la vente d'une carte-cadeau n'est pas taxée.

## Stockage

Les services lisent un dépôt : avec DoctrineBundle, le module les enregistre avec son adaptateur Doctrine — générez une
migration pour ses deux tables — ; sans lui, seul le vendable de carte-cadeau est enregistré, et l'application déclare
le reste avec son propre adaptateur.
