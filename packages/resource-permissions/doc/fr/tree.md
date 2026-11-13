<!-- phil-dev source-sha256=3e4bd8e49234a1b99099f8e42ab23b3f2da3f94f20675c0015095bcbe0ee773b -->
# Permissions dans un arbre

`Tree\TreePermissions` donne à chaque nœud d'un arbre — un livre, un chapitre, une page ; une collection et ses
éléments — des entrées par action, héritées par les nœuds en dessous.

```php
use Phil\ResourcePermissions\Tree\Grantee;
use Phil\ResourcePermissions\Tree\TreePermissions;

$tree = new TreePermissions($store);              // parent links on the relation "parent"
$tree->move($chapter, $book);                     // $chapter under $book; null puts it at the root
$tree->allow($book, 'view', Grantee::role($editor));
$tree->deny($chapter, 'view', Grantee::everyoneElse());

$decision = $tree->decide($page, 'view', [$editor, $reviewer]);
$decision->decision;  // Decision::Allow, Deny or Undecided
$decision->node;      // the node whose entries decided
$decision->grantees;  // the roles, or everyone else, of those entries
```

## La règle

De la ressource jusqu'à la racine, le premier nœud qui a une entrée pour l'un des rôles du membre, ou pour tous les
autres, décide :

1. **Le nœud le plus proche l'emporte** : une entrée sur une page prime sur celle de son livre.
2. **Sur ce nœud, les rôles du membre passent avant tous les autres** : « tous les autres » désigne tous les membres
   dont aucun rôle n'a d'entrée à cet endroit.
3. **Au même niveau, un refus l'emporte sur une permission**, quel que soit le rôle qui le porte.

Si aucun nœud de la ressource à la racine ne convient, la décision reste `Undecided` : la valeur par défaut de
l'application s'applique — les permissions du site pour les rôles du membre, par exemple.

`allow()` et `deny()` se remplacent l'un l'autre pour le même bénéficiaire ; `clear()` retire l'entrée pour que le nœud
hérite de nouveau ; `entries()` liste les entrées d'un nœud pour une action, les refus d'abord.

## Dans le magasin

Les entrées et les liens sont des tuples du magasin, ceux que lit le modèle de relations :

| Fait | Tuple |
|---|---|
| `$chapter` sous `$book` | `chapter:2#parent@book:1` |
| Les éditeurs peuvent voir `$book` | `book:1#allow.view@role:editor` |
| Tous les autres ne peuvent pas voir `$chapter` | `chapter:2#deny.view@everyone:*` |

Un modèle dont le `viewer` est `fromRelated('parent', 'viewer')` suit les mêmes liens. Une action compte de 1 à 58
caractères, selon la règle du nom d'une relation.

## Limites

Une décision lit au plus 64 rôles, une lecture du magasin par nœud, et parcourt au plus `$maxDepth` nœuds — 64 par
défaut —, au-delà de quoi elle lève `TraversalBudgetExceeded`. Un nœud à deux parents, ou qui est son propre ancêtre,
lève `InvalidTree`. `move()` remplace tous les parents qu'un nœud avait.
