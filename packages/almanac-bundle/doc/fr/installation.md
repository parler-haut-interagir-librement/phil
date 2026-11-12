<!-- phil-dev source-sha256=ff3ac09174f49cad1664b9144ac2a30bd53541088f679adbc5ef5edd4fc846b6 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | n'importe laquelle | Les noms dans les messages, coupés en caractères |
| `phil/almanac` | ^0.1@dev | Les dates et leurs règles |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/form`, `symfony/options-resolver`, `symfony/validator` | ^8.2 | Le formulaire d'une date stockée et sa validation |
| `doctrine/orm`, `symfony/uid` | facultatif | Les dates stockées, avec `database: true` |
| `twig/twig` | facultatif | `almanac_dates`, `sun_times`, `almanac_eclipses` |

```sh
composer require phil/almanac-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\AlmanacBundle\PhilAlmanacBundle::class => ['all' => true],
];
```

Avec `database: true` — ou toute valeur autre que `false`, comme une variable d'environnement —, le bundle déclare le
mappage de son entité pour Doctrine, et les migrations de l'application créent la table
([stored-dates.md](stored-dates.md)). Sans elle, l'entité n'est pas mappée et aucune migration ne crée la table.
