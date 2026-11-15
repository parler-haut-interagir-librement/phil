# phil/doctrine-behaviours-bundle

**Timestamps, blame, soft delete, trees and eight more behaviours, switched on by configuration in a Symfony application.**

phil/doctrine-behaviours-bundle brings `phil/doctrine-behaviours` into a Symfony application. It registers the listeners
of the behaviours the configuration switches on with DoctrineBundle's event manager, the `Tree` reader as a service, and
the `phil_soft_deleted` and `phil_deactivated` filters, enabled on the default entity manager. Blame names the user
Symfony Security authenticated, when the security bundle is installed. Positions are on, with the `Positions` service,
and so are translations, with the `Translations` service. Ownership, publication by workflow, slugs, versions and dated
identifiers are switched on one by one. With `phil/privacy-bundle` installed, the blame and the owner answer its access
and erasure requests.

It is for a Symfony application on Doctrine ORM that wants these behaviours without wiring listeners by hand. A
behaviour that needs nothing more is on, one that needs a package or a setting is off until asked for, and each can be
switched off. An application with several entity managers turns the
filter off here and adds it to the managers that need it. An application coming from the Gedmo extensions finds the
equivalent of each option in the documentation. Outside Symfony, `phil/doctrine-behaviours` is wired by hand.

The bundle holds no behaviour of its own. It maps configuration to the library's listeners, uses the Symfony clock for
timestamps and soft delete, and gives the library's `BlameResolver` port an adapter on Symfony Security's token
storage.

## Status

| Part | State |
|---|---|
| Timestamps, blame, soft delete and trees, each switched on or off | Implemented and tested |
| Blame on the authenticated user, by identifier or by a method of the user class | Implemented and tested |
| `BlameEraser` as a public service | Implemented and tested |
| The filters added to the default entity manager | Implemented and tested |
| Deactivation, owner, publication, slug, versions, dated identifier, each switched on or off | Implemented and tested |
| The owner of the authenticated user, by a method that must be named | Implemented and tested |
| The state machine of publication, declared for `workflow.registry` | Implemented and tested |
| Going back to a past version under a right of its own | Implemented and tested |
| Positions within the group `positionGroup()` names, with the public `Positions` service | Implemented and tested |
| Slugs unique in their table, or within the scope `slugScope()` names | Implemented and tested |
| Translations, one row per record and locale with no source to compile, the unique index of each translation table and the public `Translations` service | Implemented and tested |
| The blame and the owner in the access and erasure requests of `phil/privacy-bundle`, only when it is installed | Implemented and tested |
| Instants from the Symfony clock written in UTC, in the column types the library declares | Implemented and tested |
| Worker runtimes: `TreeListener` reset with `kernel.reset`, every other service stateless | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | Each behaviour, the filter, the blame resolver, privacy requests. |
| [Coming from the Gedmo extensions](doc/gedmo.md) | What each part of them becomes. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle::class => ['all' => true],
```

Timestamps, blame, soft delete, trees and deactivation are on; an entity takes one by implementing its interface and
using its trait. The others are one line of configuration away.

```yaml
phil_doctrine_behaviours:
    owner: { user_method: getUuid }
    publication: true
    slug: true
    versions: true
    dated_identifier: true
```

## Requirements

| Package | Version | |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/doctrine-behaviours` | ^0.1 | required |
| `doctrine/doctrine-bundle` | ^3.3 | required |
| `symfony/clock`, `symfony/config`, `symfony/dependency-injection` | ^8.2 | required |
| `symfony/security-core` | ^8.2 | optional: the blame and owner of the authenticated user, the right to go back to a past version |
| `symfony/workflow` | ^8.2 | optional: publication |
| `phil/slug-bundle` | ^0.1 | optional: slugs |
| `symfony/uid` | ^8.2 | optional: dated identifiers |
| `phil/privacy-bundle` | ^0.1 | optional: the blame and the owner in privacy requests |

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
