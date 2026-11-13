<!-- phil-dev source-sha256=05df9204764dcd921b7549099e3c15f7bea8577334b50b8eda0783342e971a2e -->
# Stockage

Deux magasins conservent les automatisations : `Definitions`, chaque version de chaque définition, et `RunStore`, les
exécutions.

| Magasin | Usage |
|---|---|
| `InMemoryDefinitions`, `InMemoryRunStore` | Les tests, les définitions écrites en code, un processus qui exécute ses automatisations d'un coup |
| `Dbal\DbalDefinitions`, `Dbal\DbalRunStore` | Une table chacun dans une connexion Doctrine DBAL, sur PostgreSQL, MySQL, MariaDB ou SQLite |

## Les tables

```php
use Phil\Automations\Dbal\{DbalDefinitions, DbalRunStore};

$definitions = new DbalDefinitions($connection, $clock);            // table phil_automation_definition
$runs = new DbalRunStore($connection);                               // table phil_automation_run

$schema = $runs->configureSchema($definitions->configureSchema($schema));
```

`configureSchema()` ajoute la table d'un magasin à un schéma qui n'en a pas — le schéma que construit une migration, ou
celui que génère l'ORM, depuis un écouteur de son événement `postGenerateSchema`. Un nom de table peut être donné comme
dernier argument de chaque magasin : lettres minuscules, chiffres et `_`, 63 caractères au plus.

`phil_automation_definition` a une ligne par version : l'identifiant de l'automatisation, la version, le type de
déclencheur, si c'est la version publiée activée, le JSON qu'écrit `DefinitionWriter`, et le moment de sa publication.
Une version une fois conservée ne change jamais : la publier de nouveau avec d'autres étapes est refusé.

`phil_automation_run` a une ligne par exécution : son identifiant, son automatisation, sa version, son sujet, son état,
son étape, l'instant où elle continue, sa tentative, sa charge utile et les étapes d'action faites en JSON, la raison
d'un échec, sa révision, et le moment où elle a démarré et celui de son dernier changement. Les instants sont des
horodatages Unix. Chaque valeur est un identifiant : la table ne contient aucun renseignement personnel que l'application
n'a pas mis dans un sujet ou une charge utile.

## Créer une fois, sauvegarder une fois

Une exécution est créée par une insertion qui laisse en paix une ligne du même identifiant — `ON CONFLICT DO NOTHING`
sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite. Elle est sauvegardée par un
`UPDATE … WHERE id = ? AND revision = ?` qui augmente la révision : de deux *workers*, un seul change la ligne. Les
autres plateformes sont refusées à la première création.

## La purge

`RunStore::purge($before)` oublie les exécutions terminées dont le dernier changement précède un instant ; les
exécutions en attente ou en cours restent. Appelez-la depuis une tâche planifiée :

```php
$runs->purge($clock->now()->modify('-90 days'));
```
