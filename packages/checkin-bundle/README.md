# phil/checkin-bundle

**Check-in at the door of a Symfony application: scanned online, decided offline, recorded once.**

phil/checkin-bundle puts `phil/checkin` in a Symfony application. It builds the desk on the application's resolvers
of codes, keeps the check-ins in a table of the application's database, and gives a device of the staff three
endpoints — scan a code, download the offline manifest of a checkpoint, send the journal kept offline — and a Stimulus
controller that uses them: a field for a scanner or a keyboard, the result announced in a live region, and, when the
network drops, the same decisions made from the manifest and synchronised once it is back.

It is the core that ticketing, registrations, memberships and attendance build on: each brings its credentials
through a `CredentialResolver`, and, for offline devices, a `CredentialCatalog`, tagged by autoconfiguration and asked
together with the others. Each checkpoint may add an admission
rule in Symfony's ExpressionLanguage — an age, a product, an hour —, applied online and to the offline manifest alike.
Who may check in at a checkpoint is the application's security, through a voter on one attribute; nobody may without
one. A camera scanner hands its codes to the controller through a DOM event, and every scan recorded reaches the
application's listeners as an event.

The endpoints trust only the controller of the site: its own header, the browser's fetch metadata and Symfony's
stateless CSRF token. Codes are never stored, never logged, and never leave the server: an offline manifest carries
keyed fingerprints of them, and the holders' names only when the application asks for them.

## Status

| Part | State |
|---|---|
| The desk on the tagged resolvers, the policy and the offline services from configuration | Implemented and tested |
| A module's admission plugged in by one service, resolver and catalog, the catalogs asked together | Implemented and tested |
| The admission rule of each checkpoint, in Symfony's ExpressionLanguage, online and in the manifests | Implemented and tested |
| The Doctrine ledger, its table joined to the schema the ORM generates | Implemented and tested |
| The scan, manifest and synchronisation endpoints, their CSRF and authorization checks | Implemented and tested |
| The scans recorded, dispatched to the application's listeners | Implemented and tested |
| The desk controller, its manifest and journal kept in IndexedDB, in English and French | Implemented and tested, in Node.js and in Chrome |
| Each device offline on its own, synchronised afterwards, without a relay on the site | Implemented and tested |
| Reading codes with a camera | Not in this bundle: a reader dispatches `phil-checkin:code` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the routes, the table, the security. |
| [Configuration](doc/configuration.md) | Every key of `phil_checkin`. |
| [Endpoints](doc/endpoints.md) | The three endpoints, their bodies, answers and errors. |
| [JavaScript](doc/javascript.md) | The desk controller, its configuration, offline mode and events. |
| [Extension points](doc/extension-points.md) | Rules, attributes, resolvers, catalogs, a module's admission, ledger, voter, readers, events, and the packages built on the bundle. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application's check-in. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/checkin-bundle
```

```yaml
# config/packages/phil_checkin.yaml
phil_checkin:
    enabled: true
    catalog: App\Checkin\TicketCatalog
```

```yaml
# config/routes/phil_checkin.yaml
phil_checkin:
    resource: '@PhilCheckinBundle/config/routes.php'
    prefix: /_checkin
```

```twig
<form data-controller="phil--checkin-bundle--checkin-desk"
      data-phil--checkin-bundle--checkin-desk-config-value="{{ desk|json_encode }}"
      data-action="phil--checkin-bundle--checkin-desk#submit">
    <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus>
    <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
</form>
```

`desk` is `DeskConfig::for(CheckpointId::of('main-door'))`; the resolver, the catalog and the voter are the
application's: see [Extension points](doc/extension-points.md).

## Requirements

PHP 8.5 or later, Symfony 8.2 with its CSRF protection on, Doctrine DBAL 4.5, and `phil/checkin`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
