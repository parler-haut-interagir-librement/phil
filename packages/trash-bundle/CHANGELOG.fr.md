<!-- phil-dev source-sha256=c4e41c57f2675854329a5a1a4b160c8092bcd9bb9ff2a54eb665757e8e9692c5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/trash-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.1-alpha.

### Modifié

- Exige la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal` 4.6), and of each development
  requirement (`doctrine/doctrine-bundle` 3.4, `doctrine/persistence` 4.2).

## 0.2.0-alpha - 2026-11-18

Les clés des éléments mis à la corbeille se comparent octet par octet sur MySQL et MariaDB. Voyez [Mise à niveau depuis
0.1](doc/fr/installation.md#mise-à-niveau-depuis-01).

### Ajouté

- `Infrastructure\Doctrine\BinaryKeysListener`, sur l'événement `loadClassMetadata` de l'ORM, et ses `FIELDS` : les
  champs des entités du bundle qui se comparent octet par octet sur MySQL et MariaDB.

### Modifié

- Sur MySQL et MariaDB, `id`, `resource_key`, `resource_id`, `parent` et `trashed_by` de `phil_trash_item` prennent le
  jeu de caractères `utf8mb4` et la collation `utf8mb4_bin` dans le schéma que génère l'ORM, par
  `Infrastructure\Doctrine\BinaryKeysListener`. PostgreSQL et SQLite gardent leur valeur par défaut, qui compare déjà
  les octets.
- `doctrine/dbal` est exigé, déjà exigé par `doctrine/orm` : `BinaryKeysListener` lit la plateforme de la connexion.

### Sécurité

- Sous la collation par défaut insensible à la casse de MySQL et de MariaDB, les éléments mis à la corbeille de la
  ressource `Doc-A` étaient trouvés, restaurés et purgés pour `doc-a`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- L'entité `TrashItem`, les exceptions `TrashException`, `InvalidTrashItem`, `ParentMissing` et `UnknownResource`, et le
  port `TrashItemRepository`. Les ressources sont des `ResourceReference` de `phil/resource`, une dépendance, dont une
  référence malformée lance `InvalidResource`.
- `Trash`, qui prend une `ClockInterface` obligatoire ; `TrashHandler`, dont `remove()` retire des rangées sans *flush*
  et laisse les fichiers à `purge()` ; `Captured` ; les événements `ResourceTrashed`, `ResourceRestored` et
  `TrashItemPurged`.
- `DoctrineTrashItemRepository`.
- `PhilTrashBundle`, alias d'extension `phil_trash`, clés `retention` et `retention_by_kind` ; il branche le service
  `clock` du conteneur.
- `PurgeTrashCommand`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
