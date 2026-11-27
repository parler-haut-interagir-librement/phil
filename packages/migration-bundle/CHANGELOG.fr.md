<!-- phil-dev source-sha256=d60a7c2bb6ea5dc8a4011c41e0e0b67a3e31511ff2c9c137253d53d371f5a333 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/migration-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4).

## 0.1.2-alpha - 2026-11-18

Une note qu'apportent plusieurs migrations d'une exécution est annoncée une fois sur chaque canal. Voyez [Une migration
qui annonce](doc/fr/migrations.md#une-migration-qui-annonce).

### Corrigé

- Une note qu'apportent deux migrations d'un même `doctrine:migrations:migrate`, comme `* Libraries updated.` ajoutée
  par une aide de l'application, est annoncée une fois sur chaque canal, là où elle vient en premier, au lieu de deux
  fois. Il exige `phil/migration` `^0.1.1@dev`, qui donne une fois chaque note, et chaque nouvelle, d'une exécution.

## 0.1.1-alpha - 2026-11-14

### Corrigé

- Le bundle s'installe à côté de DoctrineMigrationsBundle 4.0, la version qu'obtient une application Symfony 8.2 : il
  exige `doctrine/doctrine-migrations-bundle` `^4.0`. Des versions 3.x, seule 3.7.0 acceptait Symfony 8, et seulement
  parce qu'elle ne déclarait pas sa dépendance à `symfony/http-kernel`.
- Les migrations du conteneur, avec `enable_service_migrations`, reçoivent les annonces : chaque migration `Announcing`
  étiquetée `doctrine_migrations.migration` les reçoit par `announceWith()` à sa construction, au lieu d'échouer avec
  `AnnouncementsMissing`.
- La fabrique de migrations et le migrateur sont décorés après les autres décorateurs du même service, avec la priorité
  `-1`, pour que ce que retourne la fabrique des migrations de services reçoive aussi les annonces.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilMigrationBundle` et la configuration `phil_migration` : des canaux par nom avec `transport`, `locale`, `content`,
  `title`, `continued`, `heading`, `link` et `max_length`.
- `AnnouncementServices` : les annonces, et, avec des canaux, l'annonceur et `ChatterPublisher` sur le chatter.
- `AnnouncingMigrationsPass` : la fabrique de migrations et le migrateur de DoctrineMigrationsBundle enveloppés, ou ceux
  que l'application a donnés dans `doctrine_migrations.services` décorés.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
