# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares and is tested on |
| `symfony/dependency-injection` | ^8.2 | The bundle class is an `AbstractBundle`; no `symfony/http-kernel` or `symfony/framework-bundle` is required |
| `symfony/config` | ^8.2 | The configuration tree of the bundle |
| `symfony/messenger` | ^8.2 | The two bus adapters |
| `symfony/uid` | ^8.2 | UUIDv7 identifiers |
| `doctrine/dbal` | ^4.2, optional | Only for the identifier column type; versions below 4.2 are a declared `conflict` |

`Domain\` depends on `symfony/uid` alone. An application that restricts what its own `Domain` layer may import
can allow `Phil\DddBundle\Domain\` there without opening anything else: no container, no configuration, no I/O.

## Installing

The bundle is not published on Packagist yet. Install it from the repository that holds it, through a Composer
repository of type `path` or `vcs`:

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/ddd-bundle" }
    ],
    "require": {
        "phil/ddd-bundle": "^0.1@dev"
    }
}
```

Once published:

```bash
composer require phil/ddd-bundle
```

To store identifiers through Doctrine, also require DBAL (4.2 or later):

```bash
composer require doctrine/dbal
```

## Registering the bundle

```php
// config/bundles.php
return [
    // …
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
];
```

## Declaring the Messenger buses

**The bundle does not declare any Messenger bus.** It plugs into two buses the application declares, because
the middleware of each bus is a decision of the application: a transaction around commands, none around
queries. By default the bundle expects the service ids `command.bus` and `query.bus`:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus:
                middleware:
                    - validation
                    - doctrine_transaction
            query.bus: ~
```

- `doctrine_transaction` requires DoctrineBundle; without Doctrine, choose the middleware that gives commands
  their transaction in your persistence layer.
- The query bus must stay synchronous: `QueryBus::ask()` reads the result of the one handler that ran, and a
  query routed to a transport has not run yet when `ask()` returns (see
  [command-and-query-buses.md](command-and-query-buses.md)).
- Other bus ids are fine: name them in `phil_ddd` (see [configuration.md](configuration.md)).

## Making handlers autoconfigured

The bundle tags handlers through autoconfiguration. The application's services must therefore be autoconfigured,
which is the default of a Symfony application:

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
```

A handler then only implements `CommandHandler` or `QueryHandler`; no `#[AsMessageHandler]` attribute and no
`bus` option is needed. See [command-and-query-buses.md](command-and-query-buses.md).

## Registering identifier column types

Optional, and only with DBAL. Each identifier class gets its own subclass of `IdentifierType`, registered under a
type name of the application's choosing. See [doctrine-identifier-type.md](doctrine-identifier-type.md).

## Checking the installation

```bash
bin/console debug:container 'Phil\DddBundle\Application\Bus\CommandBus'
bin/console debug:messenger
```

The first command shows the alias to `MessengerCommandBus`; the second lists each handler under the bus its marker
chose.
