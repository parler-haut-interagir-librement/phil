# phil/status-page-bundle

**The status page of a service — components, incidents, planned maintenances and history — served by the
application, and a static snapshot of it pushed outside the monitored infrastructure.**

phil/status-page-bundle serves a status page at `/status`, its JSON at `/status.json` and its Atom feed at
`/status.atom`. The page shows each **component** in its state — operational, under maintenance, degraded, partly
down, down —, the **incidents** going on with their thread of updates, the **maintenances** in progress and upcoming,
and the incidents resolved within the **history**. Each component's state comes from the checks and scheduled tasks
of `phil/monitoring-bundle` when it is installed, else from the application's configuration; incidents and
maintenances come from the configuration, a JSON file the operators edit, or a store of the application's own.

A page served by the site it describes is gone when the site is down: the bundle therefore writes a **snapshot** —
`index.html`, `status.json` and `feed.atom` — and pushes it to targets outside the monitored infrastructure, through a
port with named adapters: a directory, a Flysystem storage, or a publisher of the application's own. The console
command `phil:status-page:push` pushes it, and so does the Scheduler at the frequency the configuration gives.

The page works without any script, sets no cookie, reads no session and loads nothing from another site. Its parts
are recipes of the PHIL UX kit — Card, Badge —, installed in the application by the Symfony UX Toolkit, used as they
are.

It is a Symfony bundle; `phil/monitoring-bundle`, Flysystem, the console, Messenger and the Scheduler are optional.

## Status

| Part | State |
|---|---|
| Components, incidents with their updates, planned maintenances, history | Implemented and tested |
| States from the configuration, or from the checks and tasks of `phil/monitoring-bundle`, detected | Implemented and tested |
| Incidents and maintenances from the configuration, a JSON file or a service of the application | Implemented and tested |
| The page as HTML, JSON and Atom, without script, cookie nor session | Implemented and tested |
| Templates drawn with the recipes of the PHIL UX kit | Implemented and tested |
| Snapshot pushed to a directory, a Flysystem storage or a publisher of the application | Implemented and tested |
| `phil:status-page:push`, Messenger message and Scheduler schedule | Implemented and tested |
| Fuzz target of the records reader | Implemented and tested |
| Flex recipe | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The page](doc/page.md) | What the page shows, how a state is worked out, the JSON and the feed. |
| [Incidents and maintenances](doc/records.md) | The format of the records, where they are kept. |
| [Snapshot](doc/snapshot.md) | Targets, the command, Messenger and the Scheduler. |
| [Extension points](doc/extension-points.md) | The services to replace and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/status-page-bundle
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_status_page.yaml
phil_status_page:
    title: Example status
    components:
        website: { name: Website, checks: [database] }
        api: { name: API, group: Platform, checks: [database, cache] }
    records:
        file: '%kernel.project_dir%/var/status/records.json'
    snapshot:
        base_url: 'https://status.example.org/'
        schedule: '5 minutes'
        targets:
            mirror: { type: flysystem, storage: status.storage }
```

```yaml
# config/routes/phil_status_page.yaml
phil_status_page:
    resource: '@PhilStatusPageBundle/config/routes.php'
```

## Requirements

PHP 8.5 or later with `ext-dom` and `ext-mbstring`, Symfony 8.2 or later, Twig 3.21 and Symfony UX Twig Component
3.7; `phil/url`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
