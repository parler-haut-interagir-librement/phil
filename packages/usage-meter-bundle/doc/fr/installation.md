<!-- phil-dev source-sha256=cb521f18d439aad58b0871b521cea346dca892f3adec101ddeeac12aa6a05449 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Comme `phil/usage-meter` |
| `phil/usage-meter` | Les compteurs, les périodes, les stockages et `UsageMeter` |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `phil/rate-limit-bundle` | Les règles qui nomment les limiteurs de débit, et la réponse 429 |
| `symfony/http-foundation`, `symfony/http-kernel` 8.2 | Les requêtes que comptent les limiteurs de débit, la réponse 400 d'un `Idempotency-Key` mal formé |
| `symfony/console` 8.2 | `phil:usage-meter:purge` |
| `psr/clock` | Le moment compté, tiré de l'horloge de l'application |
| `doctrine/dbal` 4.5, facultatif | Le stockage `dbal`, la valeur par défaut |
| `doctrine/orm` 3.7, facultatif | Les tables de compteurs dans le schéma que génère l'ORM |
| `phil/commerce-subscription-bundle`, facultatif | Les limites lues dans les fonctionnalités des offres d'un abonné |

## Installer

```sh
composer require phil/usage-meter-bundle
```

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_usage_meter.yaml`, qui garde les
compteurs sur la connexion DBAL `default` et ne déclare encore aucun compteur ; Flex active aussi
`phil/rate-limit-bundle`. La recette se trouve dans le dépôt de recettes de PHIL, lu dès que l'application nomme ce
dépôt dans `extra.symfony.endpoint`. Sans lui, ajoutez les bundles à `config/bundles.php`, y compris celui de
`phil/rate-limit-bundle` quand des limiteurs de débit sont déclarés :

```php
return [
    // ...
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
    Phil\UsageMeterBundle\PhilUsageMeterBundle::class => ['all' => true],
];
```

Le bundle lit le service `clock` de Symfony, que FrameworkBundle enregistre, et le paramètre `kernel.secret` pour les
empreintes des clés d'idempotence.

## Les tables

Le stockage par défaut garde les compteurs dans deux tables de la connexion DBAL `default`, `phil_usage_counter` et
`phil_usage_key` ([Configuration](configuration.md) les renomme ou choisit une autre connexion). Avec l'ORM, le bundle
les ajoute au schéma qu'il génère pour cette connexion, et les commandes habituelles s'en occupent :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

`doctrine:schema:validate` les garde ensuite à jour. Sans l'ORM, une migration les construit à partir de
`DbalUsageStore::configureSchema()`, comme le montre la documentation du stockage de `phil/usage-meter`.

Le stockage DBAL fonctionne sur PostgreSQL, MySQL, MariaDB et SQLite. `store: memory` n'a besoin d'aucune table : les
compteurs durent autant que le processus, ce qui convient aux tests et aux commandes.
