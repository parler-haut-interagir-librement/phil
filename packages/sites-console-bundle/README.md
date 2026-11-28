# phil/sites-console-bundle

**Operate many Symfony sites from one console: versions and health pulled by signed requests, alerts, settings
pushed once, batched updates that stop on failure.**

phil/sites-console-bundle is a Symfony bundle with two halves. On each site, the **agent** answers signed endpoints:
`status` — the versions read from `composer.lock` and the health from the checks of `phil/monitoring-bundle` —,
`configuration` — common settings written through a port of the site —, `update` — a package brought to a version
through the site's own pipeline — and `keys`, through which the console rotates its own key. On the console, the
**console** keeps a registry of sites, pulls each one, keeps snapshots, compares the versions of the watched packages
across sites, raises alerts, pushes settings to many sites at once and runs update plans in batches that stop at the
first failure, with a report per site and a journal of everything done.

Every exchange is signed both ways with Ed25519 keys: the console signs each request for one site, one action and one
moment; the site signs its answer over the request's nonce. The console keeps the public key of each site and no
credential of any site; who may read or act in the console comes from the roles of the user's token, which the firewall
fills from the identity provider. The console key rotates without cutting any site off: each agent accepts the current
and the next key during a rotation, which stops at the first failure and resumes. A push, an update or a key change
delivered twice is applied once, through `phil/idempotency`. The bundle does not deploy or host sites, and never runs
Composer on a server.

## Status

| Part | State |
|---|---|
| Signed protocol: Ed25519 requests and responses, date window, nonces kept against replay, site bound in each signature | Implemented and tested |
| Rotation of the console key: two keys accepted during a rotation, next key staged then previous key retired on every site, stop at the first failure, resumption, journal | Implemented and tested |
| Agent: `status` from `composer.lock` and `phil/monitoring-bundle`, `configuration` through `ConfigurationApplier` with an allow-list of keys, `update` through `UpdateRunner`, each once per identifier | Implemented and tested |
| Console: registry of sites, pulls and snapshots, alerts on reachability, health and versions behind, `SiteAlertRaised` | Implemented and tested |
| Configuration pushes and update plans in batches, stop at the first failure, report per site, journal | Implemented and tested |
| Screens drawn with the recipes of the PHIL UX kit, roles from the user's token, CSRF on every action | Implemented and tested |
| Commands, Messenger handlers, Doctrine stores on SQLite, MySQL 8.4 and PostgreSQL 17, memory stores for tests | Implemented and tested |
| External security review | Required before a beta version — see [Security model](doc/security-model.md) |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle on the console and on each site, the routes, the tables and the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option of the console and of the agent, with an example. |
| [Operating sites](doc/operations.md) | Registering sites, pulling, alerts, versions side by side, pushes, update plans, key rotation and the journal. |
| [The agent](doc/agent.md) | The endpoints, what they answer, and the applier and runner of a site. |
| [Security model](doc/security-model.md) | Keys and their rotation, signatures, replay, roles, what the console keeps and what it never does. |
| [Extension points](doc/extension-points.md) | The ports to implement, the events to listen to, the templates to override. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, its fuzz targets. |
| [Reference](doc/reference.md) | Every class, service, command, route and table of the bundle. |

## Quick start

```sh
php bin/console phil:sites-console:keys    # once for the console, once for each site
```

```yaml
# The console
phil_sites_console:
    console:
        enabled: true
        private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'

# Each site
phil_sites_console:
    agent:
        enabled: true
        site_id: shop
        private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
        console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
```

```sh
php bin/console phil:sites-console:site:add shop https://shop.example.com/_sites-console <public key of the site>
php bin/console phil:sites-console:pull
```

## Requirements

PHP 8.5 or later with `ext-sodium` and `ext-mbstring`, and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
