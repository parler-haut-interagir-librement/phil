# phil/resource-permissions

**Who may do what on each resource: relations between subjects and objects, inherited through trees and groups, checked
and listed within a budget.**

phil/resource-permissions answers access questions from facts written as tuples — *subject, relation, object*:
`member:ann` is a `viewer` of `doc:42`, the members of `group:editors` are `editor`s of `folder:7`, `doc:42` has
`folder:7` as its `parent`. A model declares, per kind of object, its relations and how each one is computed: from the
tuples written on it, from another relation of the same object (every editor is a viewer), from a relation of the
objects it points at (the viewers of the parent folder view the document), or a union, an intersection or an exclusion
of those. `check()` says whether a subject holds a relation, `explain()` gives the chain of tuples that grants it, and
`objects()` lists the objects of a kind on which a subject holds it, for a query's `IN (…)`.

On the same tuples, `TreePermissions` gives the permissions per resource of a wiki, a library or a set of campaign
secrets: each node of a tree may hold entries that allow or deny an action to a role or to everyone else, inherited
down the tree, where the most specific entry wins — the nearest node, then a role before everyone else, then a denial
before an allowance.

It is for any PHP application whose access follows its content: shared documents and folders, groups inside groups,
items visible to members only, access that expires on a date. Every walk is bounded by a `Budget` — depth, reads of
the store, subject sets behind one relation, candidates of a listing —, a cycle is cut on its own, and a walk past its
budget throws rather than answer, so a deep or hostile graph never turns into a slow page or a wrong yes.

It is a plain PHP library on `phil/resource` and `psr/clock`. The tuples live in a store: in memory for tests and
single runs, or in one table of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite, indexed both ways
so that a check and a long listing each stay a few indexed reads. A tuple may carry an expiry: from that second on,
no store reads it.

## Status

| Part | State |
|---|---|
| Subjects (one object, every object of a kind, a subject set), tuples with an optional expiry | Implemented and tested |
| The model: kinds, relations, direct, computed and inherited rewrites, union, intersection and exclusion, checked when built | Implemented and tested |
| `check()`, `explain()` and `objects()`, bounded by a budget, cycles cut, exclusions closed on a cut | Implemented and tested |
| `RelationWriter`: tuples checked against the model, written or deleted at once | Implemented and tested |
| `TreePermissions`: entries per node and action for a role or everyone else, the most specific wins | Implemented and tested |
| In-memory store; Doctrine DBAL store with byte-by-byte collation, schema and ORM listener, purge, tested on MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| A fuzz target of the checker; a benchmark of a check and a listing | Implemented |
| Symfony voters, visibility, sharing levels, access groups and "view as" on this model | Implemented in `phil/access-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the services in Symfony. |
| [The model](doc/model.md) | Subjects, tuples, kinds, relations and rewrites, and how the model is checked. |
| [Checking and listing](doc/checking.md) | `check()`, `explain()`, `objects()`, the budget, cycles and expiry. |
| [Permissions in a tree](doc/tree.md) | `TreePermissions`: entries, inheritance, the most specific entry, moving nodes. |
| [Storage](doc/storage.md) | The stores, the table and its indexes, collation, the schema listener, the purge. |
| [Exceptions](doc/exceptions.md) | `ResourcePermissionsException` and its four exceptions. |
| [Extension points](doc/extension-points.md) | The ports and arguments an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmark, and how to test code that checks access. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/resource-permissions
```

```php
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Symfony\Component\Clock\NativeClock;

$model = new Model(
    TypeDefinition::of('member'),
    TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'group#member')]),
    TypeDefinition::of('folder', [
        'parent' => Rewrite::direct('folder'),
        'editor' => Rewrite::direct('member', 'group#member'),
        'viewer' => Rewrite::union(
            Rewrite::direct('member', 'member:*', 'group#member'),
            Rewrite::computed('editor'),
            Rewrite::fromRelated('parent', 'viewer'),
        ),
    ]),
);

$store = new InMemoryTupleStore(new NativeClock());
$writer = new RelationWriter($model, $store);
$writer->grant(ResourceReference::of('folder', 'root'), 'editor', Subject::set(ResourceReference::of('group', 'staff'), 'member'));
$writer->grant(ResourceReference::of('group', 'staff'), 'member', ResourceReference::of('member', 'ann'));
$writer->grant(ResourceReference::of('folder', 'guides'), 'parent', ResourceReference::of('folder', 'root'));

$checker = new RelationChecker($model, $store);
$checker->check(ResourceReference::of('member', 'ann'), 'viewer', ResourceReference::of('folder', 'guides')); // true
$checker->objects(ResourceReference::of('member', 'ann'), 'viewer', 'folder')->ids();                         // ['guides', 'root']
```

## Requirements

PHP 8.5 or later; `doctrine/dbal` 4.5 or later for the database store, `doctrine/orm` 3.7 or later for its schema
listener.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
