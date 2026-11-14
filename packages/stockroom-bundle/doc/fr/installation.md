<!-- phil-dev source-sha256=e0e02f2ddecffbeb7519f22e5bf05fb7367676e4d73c16143c5167506fa063e6 -->
# Installation

```sh
composer require phil/stockroom-bundle
```

La recette Flex enregistre le bundle et écrit `config/packages/phil_stockroom.yaml`. Sans Flex, enregistrez-le dans
`config/bundles.php` :

```php
Phil\StockroomBundle\PhilStockroomBundle::class => ['all' => true],
```

## Prérequis

- PHP 8.5 ou une version plus récente, et `phil/stockroom`.
- `doctrine/dbal` 4.5 ou une version plus récente, et DoctrineBundle pour la connexion que nomme le stockage `dbal`.
- Les composants Config et DependencyInjection de Symfony 8.2.
- Facultatifs : `doctrine/orm`, pour les tables dans le schéma que génère l'ORM ; `symfony/console`, pour
  `phil:stockroom:schema`.

Les tables fonctionnent sur PostgreSQL, MySQL, MariaDB et SQLite.

## Les tables

Avec l'ORM, l'écouteur de schéma du bundle ajoute les six tables au schéma de la connexion, comme les stockages de base
de données propres à Symfony :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

`doctrine:schema:validate` les garde ensuite à jour, et une migration ultérieure porte tout changement qui les touche.

Sans l'ORM, la commande montre les instructions qui créent les tables absentes de la connexion, et les exécute avec
`--force` :

```sh
bin/console phil:stockroom:schema           # shows the SQL
bin/console phil:stockroom:schema --force   # creates the missing tables
```

Une table déjà présente est laissée telle quelle ; la commande n'en supprime ni n'en modifie jamais.

## Avec phil/library-bundle

Quand les deux bundles sont installés, les prêts des exemplaires liés déplacent leurs articles dans cette réserve sans
aucune configuration ; voir la section sur stockroom de la documentation de `phil/library-bundle`.
