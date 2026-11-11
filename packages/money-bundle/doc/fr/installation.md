<!-- phil-dev source-sha256=43407b9592063d2c6440de03bcd7c6182dae3ff5e2d5c6611d731628d9055d87 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle et sur lequel il est testé |
| `ext-intl` | toutes | `NumberFormatter`, pour les séparateurs de la locale dans les formulaires et pour `format_money` |
| `phil/money` | ^0.1@dev | `Money` et `Currency` |
| `symfony/form` | ^8.2 | `MoneyType` |
| `symfony/validator` | ^8.2 | `AcceptedCurrency` et `MoneyRange` |
| `symfony/intl` | ^8.2 | Les données des devises : les codes qui existent, et leurs noms dans un choix |
| `symfony/options-resolver` | ^8.2 | Les options de `MoneyType` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` |

Facultatifs, chacun enregistré seulement lorsque son paquet est installé :

| Paquet | Ce qu'il ajoute |
|---|---|
| `twig/twig` ^3.21 | Le filtre `format_money` |
| `doctrine/dbal` ^4.5 | Le type de colonne `phil_currency` |
| `doctrine/orm` ^3.7 | L'association de `Money` comme objet intégrable |

## Installer

Le bundle n'est pas encore publié sur Packagist. Installez-le, ainsi que `phil/money`, à partir du dépôt qui les
contient, puis :

```sh
composer require phil/money-bundle
```

Enregistrez le bundle, si Symfony Flex ne l'a pas fait :

```php
// config/bundles.php
return [
    // ...
    Phil\MoneyBundle\PhilMoneyBundle::class => ['all' => true],
];
```

Nommez ensuite les devises qu'accepte l'application ([configuration.md](configuration.md)).
