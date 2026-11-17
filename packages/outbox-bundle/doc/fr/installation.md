<!-- phil-dev source-sha256=47d3e128268dd311c008439e7fab6d39cd5538260ff66c716707d31fa77b516a -->
# Installation

```sh
composer require phil/outbox-bundle
```

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_outbox.yaml`. Sans Flex :

```php
// config/bundles.php
Phil\OutboxBundle\PhilOutboxBundle::class => ['all' => true],
```

| Prérequis | Version |
|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` et `sodium` |
| Symfony | 8.2 ou plus récent : `config`, `dependency-injection`, `clock` |
| Doctrine DBAL | 4.5 ou plus récent, par DoctrineBundle |
| `symfony/console` | pour les commandes |
| `doctrine/orm` | facultatif, pour ajouter la table au schéma que l'ORM génère |
| `symfony/lock` | facultatif, pour une seule exécution du relais à la fois |
| `symfony/scheduler` et `symfony/messenger` | facultatifs, pour le déclencheur planifié |
| `symfony/messenger` | facultatif, pour le pont vers un bus |

## La table

Les enregistrements vivent dans une seule table, `phil_outbox` par défaut, sur la connexion DBAL de la configuration :
la connexion des tables des bibliothèques qui enregistrent, de sorte qu'un enregistrement joint leur transaction.

Avec l'ORM, le bundle ajoute la table au schéma que l'ORM génère pour cette connexion : `doctrine:migrations:diff`
écrit la migration qui la crée, et `doctrine:schema:validate` la maintient. Sans l'ORM, ou avant la première
migration :

```sh
php bin/console phil:outbox:schema          # shows the SQL
php bin/console phil:outbox:schema --force  # creates the table
```

L'identifiant, le type et les clés se comparent octet par octet : la collation `ascii_bin` sur MySQL et MariaDB, `C`
sur PostgreSQL. Les instants sont conservés en secondes depuis l'époque Unix.

## Le relais

Exécutez `phil:outbox:relay` sous un gestionnaire de processus, ou activez le déclencheur planifié : voyez
[Workers](workers.md).
