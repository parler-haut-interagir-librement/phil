# phil/edit-lock-bundle

**One writer per block: edit leases, presence and block saves pushed through Mercure, for Symfony applications.**

phil/edit-lock-bundle lets several people edit the same resource without overwriting each other. A writer who enters
a block of a document leases it: nobody else can write that block until the lease is released, expires for want of a
heartbeat, or is taken over by someone the application allows, an owner or an administrator. A lease can also cover a
whole resource, which then excludes every lease of others on its parts. Each block the holder saves is checked by
`phil/ujo`, handed to the application, and pushed to the other writers with the list of who edits what, as Turbo
Streams through a Mercure hub. In the browser, a Stimulus controller takes the leases as the writer moves from block
to block, closes and names the blocks others hold, and applies their saves to the editor of
`phil/block-editor-bundle` as they arrive. Granularity is the block: there is no merge of characters.

It is for any Symfony application where people write together: a wiki page, a shared draft, a game master's notes,
the minutes of a meeting. The application names the resources (`article:42`, `wiki/home`), says who writes and what
they may edit, and decides where saved blocks go; the bundle keeps the leases and carries the messages. A resource
need not be a document: a form, a record or a file can be leased whole, from PHP, without the controller.

It is a Symfony bundle on `phil/ujo`, Doctrine and, optionally, MercureBundle. Leases live in one table, written by
compare-and-set queries in a transaction that locks the resource's row, so that two requests racing for a block, or
for the whole resource and one of its blocks, never both win; or in memory for tests. Every endpoint checks the
controller's header and Symfony's stateless CSRF token. Without
Mercure the controller asks for presence at each heartbeat and saves are not pushed. The controller is served by
AssetMapper, with no build step. Everything is written from scratch: Mercure, Turbo Streams and UJO (Universal JSON
Output) are the formats spoken, and no line of another lock or collaboration library is copied.

## Status

| Part | State |
|---|---|
| `LeaseKey`, `Holder`, `Lease`: a resource or a block, its holder, until when | Implemented and tested |
| `EditLocks`: acquire, heartbeat, release, leave, take over, presence, purge | Implemented and tested |
| `LeaseRepository`; `DoctrineLeaseRepository` by compare-and-set, one claim at a time per resource; `InMemoryLeaseRepository` | Implemented and tested, on SQLite, and two concurrent claims on MySQL and PostgreSQL |
| `BlockSaves`: blocks checked by `phil/ujo`, written by their holder, kept by the application's `BlockStore` | Implemented and tested |
| `MercureBroadcaster`, `TurboStreams`: presence, saved and removed blocks as Turbo Streams | Implemented and tested, also against a real hub |
| `EditLockController`, routes, `ClientConfig`: the endpoints and the controller's configuration | Implemented and tested |
| The `collaboration` controller: leases that follow the writer, roster, takeover, remote saves applied to the block editor | Implemented; pure modules tested under Node, checked in a browser with two writers ([testing](doc/testing.md)) |
| Merging the characters two writers type in one block | Not part of the bundle: one writer per block |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the table, the routes, Mercure, the controller and the stylesheet. |
| [Configuration](doc/configuration.md) | Every option, and the services the application writes. |
| [Leases](doc/leases.md) | Keys, holders, durations, heartbeats, expiry, takeover, release, presence and the races between requests. |
| [Blocks](doc/blocks.md) | Saving and removing blocks, the check by `phil/ujo`, the application's block store. |
| [Mercure](doc/mercure.md) | Topics, private updates, the Turbo Streams pushed, and running without a hub. |
| [Endpoints](doc/endpoints.md) | Each route, its body, its answers, the controller's header and its CSRF token. |
| [JavaScript](doc/javascript.md) | The controller, its configuration, its modules, and how it works with the block editor. |
| [Exceptions](doc/exceptions.md) | The bundle's exceptions, and the answers of its endpoints. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the real hub, the mutation bar and the checks in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/edit-lock-bundle symfony/mercure-bundle
```

```yaml
# config/packages/phil_edit_lock.yaml
phil_edit_lock:
    holder_provider: App\Collaboration\CurrentWriter   # a HolderProvider
    policy: App\Collaboration\WritersPolicy            # an EditPolicy
    block_store: App\Collaboration\DraftBlocks          # a BlockStore, optional
```

```twig
<div data-controller="phil--edit-lock-bundle--collaboration"
     data-phil--edit-lock-bundle--collaboration-config-value="{{ collaboration|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--edit-lock-bundle--collaboration-target="presence"></aside>
</div>
```

`collaboration` is `ClientConfig::for('article:42', $holder)`, given by the page's controller.

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4 and ORM 3, `phil/ujo`. For pushed updates, MercureBundle and a Mercure
hub; for the controller, Stimulus 3 and `phil/block-editor-bundle`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
