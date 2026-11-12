# Trees

| Type | Kind | Role |
|---|---|---|
| `Contract\TreeNode` | interface | `treeId()`, `treeParent()`, `treePath()`, `treeDepth()`, `placeInTree()` |
| `TreeNodeEntity` | trait | The columns `tree_path` (750 characters) and `tree_depth`, nullable |
| `TreeListener` | listener: `postPersist`, `onFlush`, `postFlush` | Places nodes and moves branches |
| `Tree` | reader | `roots()`, `children()`, `descendants()`, `ancestors()`, `hierarchy()` |
| `Branch` | `final readonly class` | A node and the branches under it |
| `InvalidTree` | `LogicException` | A place a node cannot take |

## Paths

A node's path is the identifiers from its root down to itself: `/1/4/9/` for 9 under 4 under the root 1, at depth 2.
The entity maps its own identifier and parent; `treeId()` gives the identifier, which holds no slash, and
`treeParent()` the parent. The path is written once the node is inserted, so an identifier the database generates
works as well as one the caller gives. The entity indexes `tree_path`:

```php
#[ORM\Index(columns: ['tree_path'])]
```

## Moves

A node whose parent changed takes its new path with the flush, and its branch follows: the descendants already
loaded are written by the flush, the others by one `UPDATE` once it is over. A node cannot move under itself or one
of its descendants, nor under a parent that has no path yet: each is an `InvalidTree`. A node without a path — a
row older than the behaviour — keeps none until it is given one.

The moves wait in the listener from `onFlush` to `postFlush`, and each flush starts with none: the moves of a flush
that failed — a constraint, a deadlock — are given up with it, never applied by the next one. `TreeListener::reset()`
forgets them too; the bundle tags it `kernel.reset`.

## Reading

```php
$tree->roots(Category::class);
$tree->children($games);                                 // one level, by depth: the rest of the branch is not read
$tree->descendants($games);                              // none while the node has no path
$tree->ancestors($chess);                                // the root down to the parent
$tree->hierarchy(Category::class);                       // every root, each with its branches
$tree->hierarchy($games, fn ($a, $b) => $a->name <=> $b->name);
```

Every list is in the order of the paths: a parent before its children, siblings in the order of their identifiers;
`hierarchy()` orders siblings by the comparison given.
