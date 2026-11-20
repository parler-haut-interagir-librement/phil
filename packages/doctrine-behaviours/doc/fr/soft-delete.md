<!-- phil-dev source-sha256=8b2d8baf752bd1b3e3fec49682a517fd649b3da0a9fa5e3231a66eea6d8a80ca -->
# Suppression douce

| Type | Genre | Rôle |
|---|---|---|
| `Contract\SoftDeletable` | interface | `deletedAt()`, `isDeleted()`, `markDeleted()`, `restore()` |
| `SoftDeletableEntity` | trait | La colonne `deleted_at`, `datetime_utc_immutable`, nullable |
| `SoftDeleteListener` | écouteur : `onFlush` | Change une suppression en date de suppression |
| `SoftDeletedFilter` | filtre SQL `phil_soft_deleted` | `deleted_at IS NULL` sur chaque entité `SoftDeletable` |
| `SoftDeletes` | service | `purge()` : supprime pour de bon la ligne d'une entité `SoftDeletable` |

```php
$manager->remove($category);
$manager->flush();                              // deleted_at = now; the row stays, the entity stays managed

$manager->getFilters()->disable(SoftDeletedFilter::NAME);
$category->restore();                           // deleted_at = null
$manager->flush();

$manager->remove($category);                    // already deleted: removed for good
```

**Une première suppression garde toute la ligne** : chaque colonne reste telle qu'elle était, noms et adresses compris,
seul `deleted_at` étant renseigné. Un effacement qui supprime une seule fois une entité `SoftDeletable` n'a rien effacé.
`SoftDeletes::purge()` supprime la ligne en un seul `flush`, que l'entité ait été supprimée en douceur avant ou non :

```php
$softDeletes = new SoftDeletes($registry, $clock);  // or new SoftDeletes($manager, $clock)
$softDeletes->purge($member);                      // DELETE, and whatever else is pending is flushed with it
```

L'entité doit être gérée. Avec un `ManagerRegistry`, `purge()` prend le gestionnaire de la classe de l'entité.

Tant qu'il est actif, le filtre cache les lignes supprimées de chaque requête — `find()`, DQL, les collections d'une
relation chargées à la demande. Une clé étrangère `ON DELETE CASCADE` de la base ne s'exécute jamais, puisqu'aucune
ligne n'est supprimée.

## Cascades

L'écouteur garde une entité `SoftDeletable` supprimée en la persistant de nouveau, et cette persistance se propage comme
le disent les associations. Pour les entités auxquelles une suppression se propage :

| L'enfant est | L'association propage | L'enfant |
|---|---|---|
| `SoftDeletable` | `persist` et `remove` | Est supprimé en douceur lui aussi |
| non `SoftDeletable` | `persist` et `remove` | Reste : la ligne du parent reste, ses enfants avec elle, prêts pour `restore()` |
| non `SoftDeletable` | `remove` seulement | Est supprimé pour de bon |

Une application qui veut voir disparaître les enfants avec un parent supprimé en douceur les supprime elle-même, ou
mappe l'association avec `remove` seulement.

## Relations vers une ligne supprimée

Une relation vers un seul côté — `ManyToOne`, `OneToOne` — vers une ligne supprimée en douceur n'est pas cachée : la clé
étrangère garde son identifiant, et charger l'entité liée sous le filtre ne trouve aucune ligne, si bien que Doctrine
lève une `EntityNotFoundException`, `comment.article.title` pour un article supprimé. Les issues :

- mettre la clé étrangère à `null` quand la cible est supprimée en douceur, si la relation est nullable ;
- lire ces pages avec le filtre désactivé, et montrer la cible comme supprimée avec `isDeleted()` ;
- aller chercher la relation par un `LEFT JOIN` dans la requête : le filtre s'applique à la jointure, et la relation est
  `null`.

Une ressource qui devrait quitter sa table, avec ce qu'il faut pour la ramener, va plutôt dans `phil/trash-bundle`.

## L'ordre des écouteurs

Une fois que `SoftDeleteListener` s'est exécuté, une entité supprimée n'est plus prévue pour une suppression mais pour
une modification. Enregistrez-le après les écouteurs d'`onFlush` qui doivent voir la suppression comme telle — un index
de recherche qui retire l'entité, un journal d'audit — : Doctrine appelle les écouteurs d'un événement dans l'ordre où
ils ont été ajoutés. Le bundle lui donne une priorité sous la valeur par défaut. `postFlush` s'exécute une fois l'unité
de travail écrite, avant qu'une transaction englobante ne soit validée : voir [Écritures en bloc](bulk-writes.md).
