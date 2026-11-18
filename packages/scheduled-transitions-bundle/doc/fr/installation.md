<!-- phil-dev source-sha256=81224e01815519ffa392166d2a2fc9e6a3963065ec7fd0350dd3ff84e6b1d38a -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toute | Une erreur gardée à 255 caractères |
| `doctrine/orm` | ^3.7 | La table des transitions |
| `psr/clock` | ^1.0 | Ce qui est échu ; le service `clock` du conteneur, que FrameworkBundle enregistre une fois `symfony/clock` installé |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | La classe du bundle, sa configuration, les identifiants |
| `phil/resource` | ^0.1 | `ResourceReference`, la sorte et l'identifiant d'une ressource |
| `symfony/scheduler`, `symfony/messenger` | facultatif | Les exécutions depuis Symfony Scheduler |
| `symfony/workflow` | facultatif | Les transitions appliquées par un workflow |
| `symfony/console` | facultatif | `phil:scheduled-transitions:run` |

```sh
composer require phil/scheduled-transitions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_scheduled_transitions.yaml
phil_scheduled_transitions:
    frequency: '1 minute'   # how often Symfony Scheduler runs what is due
    batch: 100              # transitions a run applies at most
    max_attempts: 3         # tries before a failing transition is given up
    claim_for: 600          # seconds a run holds a transition it applies
```

Avec DoctrineBundle, le bundle associe son entité ; les migrations de l'application créent `phil_scheduled_transition` :
`id`, `resource_kind`, `resource_id`, `transition` (64), `due_at` (UTC), `status` (`pending`, `done`, `failed`,
`cancelled`), `attempts`, `last_error` (255), `applied_at`, `claimed_until` (UTC, la réservation de l'exécution qui
l'applique), indexée sur le statut et le moment, et sur la ressource.

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Les transitions d'une ressource se trouvent par sa
clé telle qu'elle a été écrite : celles planifiées pour `Doc-A` ne sont jamais listées ni annulées pour `doc-a`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_scheduled_transition` | `id`, `resource_kind`, `resource_id`, `transition` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT resource_kind, resource_id, COUNT(*) AS row_count FROM phil_scheduled_transition
GROUP BY resource_kind, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.
