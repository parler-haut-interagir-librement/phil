<!-- phil-dev source-sha256=b1b7262c777af489515af48c50e2c7333143b5b6d4ac89df426f35aa5d46072e -->
# Suppression douce

| Type | Genre | Rôle |
|---|---|---|
| `Contract\SoftDeletable` | interface | `deletedAt()`, `isDeleted()`, `markDeleted()`, `restore()` |
| `SoftDeletableEntity` | trait | La colonne `deleted_at`, `datetime_immutable`, nullable |
| `SoftDeleteListener` | écouteur : `onFlush` | Change une suppression en date de suppression |
| `SoftDeletedFilter` | filtre SQL `phil_soft_deleted` | `deleted_at IS NULL` sur chaque entité `SoftDeletable` |

```php
$manager->remove($category);
$manager->flush();                              // deleted_at = now; the row stays, the entity stays managed

$manager->getFilters()->disable(SoftDeletedFilter::NAME);
$category->restore();                           // deleted_at = null
$manager->flush();

$manager->remove($category);                    // already deleted: removed for good
```

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
