# phil/trash-bundle

**A trash for deleted pages and other resources: restore them, or purge them after a set time.**

phil/trash-bundle gives a Symfony application a trash. A resource goes in through a `TrashHandler` the application
writes for its kind. The handler keeps what it needs to bring the resource back, as JSON, then removes it. The
resource comes back through the same handler, under its parent, or under another one when its parent is gone. Once
its retention is over, `phil:trash:purge` deletes it for good. The bundle does not guess how an entity is rebuilt:
each kind says what to keep and how to restore it.

It is for an application where editors delete things and sometimes regret it. An administrator lists the trash, most
recent first, restores an item or lets it expire. Each step dispatches an event, `ResourceTrashed`,
`ResourceRestored` or `TrashItemPurged`, so an audit trail can record who did what. Retention is set in days, for all
kinds or kind by kind, which fits a data retention policy.

The trash itself is a Doctrine table, and resources are named through `phil/resource`. The handler is the one port: a
trash in a single table works for any entity, with its children and its files, because the code that knows them is
the application's. The resource leaves its table and enters the trash in the same flush.

## Status

| Part | State |
|---|---|
| `TrashItem` and the repository port; `ResourceReference` of `phil/resource` | Implemented and tested |
| `Trash`: trash, restore, purge; `TrashHandler`, `Captured` | Implemented and tested |
| Events: `ResourceTrashed`, `ResourceRestored`, `TrashItemPurged` | Implemented and tested |
| `DoctrineTrashItemRepository` | Implemented and tested |
| `phil:trash:purge` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the table. |
| [Handlers](doc/handlers.md) | Writing a `TrashHandler`, trashing and restoring, a parent that is gone. |
| [Retention](doc/retention.md) | Retention by kind, the purge, the events. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$item = $trash->trash($page, trashedBy: $user->getUserIdentifier());

$trash->restore($item);                                            // under its parent
$trash->restore($item, ResourceReference::of('page', $newParent)); // its parent is gone
```

```bash
bin/console phil:trash:purge
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; Doctrine ORM 3.7; PSR-20 and PSR-14; Symfony 8.2's dependency injection,
config and UID; `phil/resource` 0.1 for resource references. Symfony Console is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
