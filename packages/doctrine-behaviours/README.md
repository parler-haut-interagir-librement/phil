# phil/doctrine-behaviours

**Twelve Doctrine behaviours, from timestamps and trees to positions, slugs, translations and versions, each one
opt-in.**

phil/doctrine-behaviours gives Doctrine ORM entities twelve behaviours. Timestamps stamp when an entity is created and
updated, in UTC. Blame records who created and last changed it, as a user identifier. Soft delete hides a removed entity
behind a filter instead of deleting it, and removing it a second time deletes it. Trees keep each node's materialized
path and depth, and the `Tree` reader gives roots, children, descendants, ancestors and the hierarchy. Deactivation
switches a record off and back on behind a filter of its own. An owner is a stable identifier, never an e-mail address.
Publication is a state of `symfony/workflow` with its dates. A position orders records within a group, and a record
moves before or after another. A slug made by `phil/slug` is kept unique in its table, or within a scope such as a
tenant, a locale or a parent. Translations keep the text of a record in a row per locale, read along a declared fallback
chain and never copied from one locale to another. Versions freeze what a record held, fork the next edit, and go back
under a right of their own. A dated identifier is a UUID v7.

It is for any application on Doctrine ORM that needs these columns kept without writing listeners of its own. Each
behaviour stands alone, so an application registers only the listeners its entities use. Trees are materialized
paths: moving a branch is one update instead of renumbering the tree. In a Symfony application,
`phil/doctrine-behaviours-bundle` registers the listeners, the filter and the blame of the authenticated user. A
record that should leave its table, ready to be brought back, belongs in `phil/trash-bundle` instead.

It is a plain PHP library on Doctrine ORM, DBAL, `psr/clock` and `phil/locale-dimensions`; `phil/slug`, `symfony/uid`
and `symfony/workflow` are needed only by the behaviours that name them. Each behaviour is an interface, a trait that
maps its columns, and a listener that keeps them. Who is acting comes from a `BlameResolver` the application provides,
such as `FixedBlame` for a command that acts for a known account, or a test.

## Status

| Part | State |
|---|---|
| `Contract\Timestampable`, `TimestampableEntity`, `TimestampListener` | Implemented and tested |
| `Contract\Blameable`, `BlameableEntity`, `BlameListener`, `Contract\BlameResolver`, `FixedBlame` | Implemented and tested |
| `BlameEraser`: a user's name found and replaced in every blamed table | Implemented and tested |
| `Contract\SoftDeletable`, `SoftDeletableEntity`, `SoftDeleteListener`, `SoftDeletedFilter` | Implemented and tested |
| `Contract\TreeNode`, `TreeNodeEntity`, `TreeListener`, `Tree`, `Branch` | Implemented and tested |
| `Deactivatable`, `DeactivatableEntity`, `DeactivationListener`, `DeactivatedFilter` | Implemented and tested |
| `OwnerId`, `Ownable`, `OwnableEntity`, `OwnerListener`, `OwnerResolver`, `FixedOwner`, `OwnerEraser` | Implemented and tested |
| `Publishable`, `PublishableEntity`, `PublicationWorkflow`, `PublicationListener` | Implemented and tested |
| `Contract\Positionable`, `PositionableEntity`, `PositionListener`, `Positions`, groups | Implemented and tested |
| `Contract\Sluggable`, `SluggableEntity`, `ScopedSluggableEntity`, `SlugListener`, scopes | Implemented and tested |
| `Contract\Translatable`, `Contract\Translation`, `TranslationEntity`, `TranslationListener`, `Translations` | Implemented and tested |
| `Versionable`, `VersionableEntity`, `VersionListener`, `Versions`, `RecordVersion`, `VersionRestoreGuard` | Implemented and tested |
| `DatedIdentifiable`, `DatedIdentifierEntity`, `DatedIdentifierListener` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Timestamps and blame](doc/timestamps-and-blame.md) | Who and when, what an entity was given and keeps, privacy and erasure. |
| [Soft delete](doc/soft-delete.md) | Removal as a date, the filter, restoring, deleting for good. |
| [Trees](doc/trees.md) | Paths, moves of a branch, reading a tree. |
| [Deactivation](doc/deactivation.md) | Switching a record off and on, the filter, how it differs from soft delete. |
| [Owner](doc/owner.md) | A stable identifier, the resolver, handing over, erasure. |
| [Publication](doc/publication.md) | The state machine, its dates, publishing at a set time. |
| [Position](doc/position.md) | Ordering within a group, moving before and after, the index. |
| [Slug](doc/slug.md) | When a slug is made, how it stays unique, in the table or within a scope. |
| [Translation](doc/translation.md) | A row per locale, read along a fallback chain, a list in one query, writing without copying, slugs and versions per locale. |
| [Versions](doc/versions.md) | Freezing, forking, going back under a right of its own. |
| [Dated identifier](doc/dated-identifier.md) | A UUID v7 as the primary key. |
| [Integration](doc/integration.md) | Registering the listeners and the filter without Symfony. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
#[ORM\Entity]
class Category implements Timestampable, Blameable, SoftDeletable, TreeNode
{
    use TimestampableEntity;
    use BlameableEntity;
    use SoftDeletableEntity;
    use TreeNodeEntity;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $parent = null;

    public function treeId(): string { return (string) $this->id; }
    public function treeParent(): ?self { return $this->parent; }
}
```

```php
$tree->hierarchy(Category::class);    // list<Branch>, each node with its children
$tree->ancestors($category);          // the root down to the parent
```

In a Symfony application, `phil/doctrine-behaviours-bundle` registers the listeners and the filter.

## Requirements

PHP 8.5 or later, Doctrine ORM 3.7 or later, DBAL 4.5, `doctrine/collections`, `psr/clock` and
`phil/locale-dimensions`; `phil/slug` for slugs,
`symfony/uid` for dated identifiers, and `symfony/workflow` for publication.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
