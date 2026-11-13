# Permissions in a tree

`Tree\TreePermissions` gives each node of a tree — a book, a chapter, a page; a collection and its items — entries per
action, inherited by the nodes below it.

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

## The rule

From the resource up to the root, the first node with an entry for one of the member's roles, or for everyone else,
decides:

1. **The nearest node wins**: an entry on a page beats one on its book.
2. **On that node, the member's roles come before everyone else**: everyone else is every member none of whose roles
   has an entry there.
3. **At the same level, a denial wins over an allowance**, whichever role carries it.

No such node from the resource to the root leaves the decision `Undecided`: the application's default applies — the
site-wide permissions of the member's roles, for example.

`allow()` and `deny()` replace each other for the same grantee; `clear()` removes the entry so that the node inherits
again; `entries()` lists a node's entries for an action, denials first.

## In the store

Entries and links are tuples of the store, the ones the relation model reads:

| Fact | Tuple |
|---|---|
| `$chapter` under `$book` | `chapter:2#parent@book:1` |
| Editors may view `$book` | `book:1#allow.view@role:editor` |
| Everyone else may not view `$chapter` | `chapter:2#deny.view@everyone:*` |

A model whose `viewer` is `fromRelated('parent', 'viewer')` follows the same links. An action is 1 to 58 characters,
under the rule of a relation's name.

## Limits

A decision reads at most 64 roles, one read of the store per node, and walks at most `$maxDepth` nodes — 64 by
default —, past which it throws `TraversalBudgetExceeded`. A node with two parents, or one that is its own ancestor,
throws `InvalidTree`. `move()` replaces every parent a node had.
