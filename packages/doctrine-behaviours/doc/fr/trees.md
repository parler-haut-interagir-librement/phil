<!-- phil-dev source-sha256=74468e7bf0227e8b637574051703351a2fba7e1bcd6126ce531bcf40c9f92e71 -->
# Arbres

| Type | Genre | Rôle |
|---|---|---|
| `Contract\TreeNode` | interface | `treeId()`, `treeParent()`, `treePath()`, `treeDepth()`, `placeInTree()` |
| `TreeNodeEntity` | trait | Les colonnes `tree_path` (750 caractères) et `tree_depth`, nullables |
| `TreeListener` | écouteur : `postPersist`, `onFlush`, `postFlush` | Place les nœuds et déplace les branches |
| `Tree` | lecteur | `roots()`, `children()`, `descendants()`, `ancestors()`, `hierarchy()` |
| `Branch` | `final readonly class` | Un nœud et les branches sous lui |
| `InvalidTree` | `LogicException` | Une place qu'un nœud ne peut pas prendre |

## Chemins

Le chemin d'un nœud est la suite des identifiants de sa racine jusqu'à lui : `/1/4/9/` pour 9 sous 4 sous la racine 1, à
la profondeur 2. L'entité mappe son propre identifiant et son parent ; `treeId()` donne l'identifiant, qui ne contient
aucune barre oblique, et `treeParent()` le parent. Le chemin est écrit une fois le nœud inséré : un identifiant que
génère la base convient aussi bien qu'un identifiant que donne l'appelant. L'entité indexe `tree_path` :

```php
#[ORM\Index(columns: ['tree_path'])]
```

## Déplacements

Un nœud dont le parent a changé prend son nouveau chemin au vidage, et sa branche suit : les descendants déjà chargés
sont écrits par le vidage, les autres par un seul `UPDATE` une fois qu'il est fini. Un nœud ne peut aller ni sous
lui-même ni sous l'un de ses descendants, ni sous un parent qui n'a pas encore de chemin : chaque cas est une
`InvalidTree`. Un nœud sans chemin — une ligne plus ancienne que le comportement — n'en a pas tant qu'on ne lui en donne
pas.

Les déplacements attendent dans l'écouteur de `onFlush` à `postFlush`, et chaque vidage commence sans aucun : ceux d'un
vidage qui a échoué — une contrainte, un interblocage — sont abandonnés avec lui, jamais appliqués par le suivant.
`TreeListener::reset()` les oublie aussi ; le bundle l'étiquette `kernel.reset`.

## Lecture

```php
$tree->roots(Category::class);
$tree->children($games);                                 // one level, by depth: the rest of the branch is not read
$tree->descendants($games);                              // none while the node has no path
$tree->ancestors($chess);                                // the root down to the parent
$tree->hierarchy(Category::class);                       // every root, each with its branches
$tree->hierarchy($games, fn ($a, $b) => $a->name <=> $b->name);
```

Chaque liste suit l'ordre des chemins : un parent avant ses enfants, les nœuds frères dans l'ordre de leurs
identifiants ; `hierarchy()` ordonne les frères selon la comparaison donnée.
