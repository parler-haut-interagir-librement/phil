<!-- phil-dev source-sha256=ff767cb96b8a9eb6b296137b4e5168dbbab19a1ef2c2d1ab27dcf8a3cc784aa5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/references-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.1-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal` 4.6), and of each development
  requirement (`doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

## 0.2.0-alpha - 2026-11-18

Les sortes, les identifiants et les champs de la table d'usages se comparent octet par octet sur MySQL et MariaDB. Voyez
[Mise à niveau depuis 0.1](doc/fr/installation.md#mise-à-niveau-depuis-01).

### Modifié

- `UsageTable::configure()` prend la plateforme de la connexion comme second argument, et l'écouteur du schéma la lui
  donne : sur MySQL et MariaDB, `usage_key`, `source_kind`, `source_id`, `field`, `target_kind` et `target_id` de
  `phil_reference_usage` prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`. PostgreSQL et SQLite
  gardent leur valeur par défaut, qui compare déjà les octets.

### Corrigé

- Sous la collation par défaut insensible à la casse de MySQL et de MariaDB, rafraîchir les usages de la source `Doc-A`
  lisait ceux de `doc-a` et les supprimait, et les usages de la cible `doc-a` étaient listés pour `Doc-A`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Usage`, les exceptions `ReferencesException`, `InvalidReference` et `StillReferenced`, et le port `UsageRepository`,
  dont `usagesOfAll()` lit d'un coup les usages de nombreuses cibles. Les ressources sont des `ResourceReference` de
  `phil/resource`, une dépendance, dont une référence malformée lance `InvalidResource`.
- `References`, avec `forgetSource()` ; `ReferenceCollector`, `ReferenceTarget` et `Link`.
- `DbalUsageRepository`, qui n'écrit que les usages qui ont changé et lit les usages de nombreuses cibles par morceaux
  de 500 par sorte ; `UsageTable`, dont la clé est un SHA-256 de ses cinq colonnes pour que chaque clé tienne dans la
  limite de MySQL, avec des index sur la cible et sur la source ; `UsageSchemaListener`.
- `PhilReferencesBundle`, alias d'extension `phil_references`, clés `connection`, `track_entities` et `block_deletion`.
- `ReferenceTrackingListener`, qui lit la référence d'une source supprimée avant le *flush*, note ce que fera un *flush*
  par gestionnaire d'entités, et l'oublie quand le *flush* échoue, que le gestionnaire d'entités est vidé ou que le
  conteneur est remis à zéro.
- `CheckReferencesCommand`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
