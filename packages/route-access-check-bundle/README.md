# phil/route-access-check-bundle

**`bin/console phil:route-access:check`: every route of the application has an access rule, or the CI step fails.**

phil/route-access-check-bundle brings `phil/route-access-check` into a Symfony application as a console command. It
checks every route of the router against the rules that protect it — `#[IsGranted]`, the firewalls and the
`access_control` entries of the security configuration, and the explicit public declarations — and exits with 1 when
a route has no rule, or contradicts itself. The report lists each route with its resolved rule, in text for a person,
in JSON for a tool, as GitHub annotations on the controllers, or as JUnit for a CI server.

It is for any Symfony application that wants its access rules reviewed by the build rather than by memory. The
security configuration is read from the services SecurityBundle builds, after every configuration file is merged, so
the check sees exactly what the application runs: `when@` blocks, PHP configuration, parameters, `route:` entries.
It registers its services in the `dev` and `test` environments only: no listener, nothing in production.

It is built on the components Symfony already ships: Config and DependencyInjection for the
configuration and the compiler pass, Console for the command, Filesystem for the baseline file.

## Status

| Part | State |
|---|---|
| The command, its four formats, its baseline and its strict mode | Implemented and tested |
| Firewalls and `access_control` read from SecurityBundle's services | Implemented and tested, against SecurityBundle in a kernel |
| Controller services whose id is not their class | Implemented and tested |
| Detectors and formats of the application, autoconfigured | Implemented and tested |
| Nothing registered outside the configured environments | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, registering the bundle for dev and test only. |
| [Configuration](doc/configuration.md) | Every key of `phil_route_access_check`. |
| [The command](doc/command.md) | Its options, exit codes, and a continuous integration step. |
| [Extension points](doc/extension-points.md) | Detectors and formats of the application, the services and parameters. |
| [Testing](doc/testing.md) | The bundle's tests, and the check as a test of the application. |
| [Reference](doc/reference.md) | Every class, service, tag and parameter of the bundle. |

The rules themselves — how each source is read, when a route is public, the statuses — are documented with the
library, in `phil/route-access-check`.

## Quick start

```sh
composer require --dev phil/route-access-check-bundle
```

```php
// config/bundles.php
Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle::class => ['dev' => true, 'test' => true],
```

```sh
bin/console phil:route-access:check --env=test
bin/console phil:route-access:check --env=test --format=github --only-problems
bin/console phil:route-access:check --env=test --generate-baseline
```

## Requirements

PHP 8.5 or later, `phil/route-access-check`, and Symfony 8.2: Config, Console, DependencyInjection, Filesystem,
HttpFoundation and Routing. SecurityBundle is read when it is installed.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
