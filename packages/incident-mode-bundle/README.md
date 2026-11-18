# phil/incident-mode-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/incident-mode-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/incident-mode-bundle)

**The incident mode of phil/incident-mode in a Symfony application: suspend sign-ups, invitations, new messages or
everything but reading for a duration that is always set, from an administration page or the console, and say so
honestly to every visitor until it ends.**

phil/incident-mode-bundle wires the library `phil/incident-mode` into Symfony. Its table lives on the DBAL connection
the application chooses, and the schema listener of the library adds it to the schema Doctrine generates. The presets
come from the configuration: the five built-in ones, the application's own, some replaced or left out. An incident is
started, extended within its preset and lifted from an administration page — protected by a role, every form posted
with a CSRF token, built from the recipes of the PHIL UX kit or of the Shadcn kit — or from four console commands, in
the name of an actor known by a stable identifier, never an e-mail address.

While an incident is in force, the checkpoints refuse what it suspends: a controller that carries
`#[SuspendedDuring('signups')]`, a route whose defaults name a restriction, a code path that calls
`Checkpoint::guard()`, a form with the option `suspended_during`. A refusal is a page that says what is suspended and
until when, in the visitor's language and the configured time zone, with the status 503 and a `Retry-After` header. A
banner and a public status page say the same. Established members are spared from the restrictions the configuration
lists, through the library's port `EstablishedMembers`, which the application points at its own trust levels.

Each incident is lifted at its end by the library's delayed Messenger message, and a recurring sweep of Symfony
Scheduler records the end of any incident whose message a transport lost. The events of the library go through
Symfony's event dispatcher; with `phil/audit-bundle` registered, every start, extension and lifting is written to its
trail, and with `phil/privacy-bundle`, the incidents a member started or lifted answer their access and erasure
requests. The services keep nothing between requests.

## Status

| Part | State |
|---|---|
| The table on a configurable connection, the schema listener, the presets of the configuration | Implemented and tested on SQLite, MySQL and PostgreSQL |
| `phil:incident:start`, `phil:incident:extend`, `phil:incident:lift`, `phil:incident:list` | Implemented and tested |
| The administration page: start from a preset, extend within it, lift, the journal a page at a time | Implemented and tested with the PHIL UX kit and the Shadcn kit |
| The lifting by the delayed Messenger message and the recurring sweep of Symfony Scheduler | Implemented and tested |
| The refusal page, the status page, the notice fragment, the banner, the Twig functions | Implemented and tested in English and French |
| `#[SuspendedDuring]`, the route default `_suspended_during`, `Checkpoint`, the option `suspended_during` of the forms | Implemented and tested |
| Established members spared through `EstablishedMembers`, visitors never | Implemented and tested |
| The audit through phil/audit-bundle, the export and erasure through phil/privacy-bundle | Implemented and tested |
| A table name of the application's choosing | Not available: the library names its table `phil_incident_activation` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the table and its migration, the routes, the transports. |
| [Configuration](doc/configuration.md) | Every option of `phil_incident_mode`, with an example. |
| [Administration](doc/administration.md) | The page, the role, the actor, the CSRF token, the kits, the layout and the console commands. |
| [Checkpoints](doc/checkpoints.md) | The attribute, the route default, the service, the forms, the refusal page, the banner and the status page. |
| [Automatic lifting](doc/automatic-lifting.md) | The delayed message, the recurring sweep, a cron job without Messenger. |
| [Extension points](doc/extension-points.md) | The services to replace, the events, the words and the templates. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/incident-mode-bundle
```

```yaml
# config/packages/phil_incident_mode.yaml
phil_incident_mode:
    spare_established: [new-posts, new-comments]
    established_members: App\Trust\EstablishedMembers
    presets:
        suspend-listings: { label: 'Suspend new listings', restrictions: [new-listings], proposed: 4h, longest: 2d }
```

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

#[SuspendedDuring('signups', methods: ['POST'])]
public function register(Request $request): Response
```

```twig
{{ include('@PhilIncidentMode/_banner.html.twig') }}
```

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later, Doctrine DBAL 4.5, Twig and the Symfony UX Twig components,
and `phil/incident-mode` 0.2. The console, the forms, Messenger, Scheduler, the ORM, `phil/audit-bundle` and
`phil/privacy-bundle` are used when they are installed.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
