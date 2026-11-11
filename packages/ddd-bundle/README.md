# phil/ddd-bundle

**The few types every bounded context shares: identifiers, domain events, errors, command and query buses.**

phil/ddd-bundle is the DDD/CQRS core of a Symfony application split into bounded contexts. It gives UUIDv7 aggregate
identifiers that the caller generates, a domain event interface, domain errors, and command and query bus ports with
their Messenger adapters. Handlers are autoconfigured onto the right bus. An optional Doctrine DBAL column type stores
identifiers in a native `uuid` column, or `BINARY(16)` where the platform has none. The bundle does not declare your
Messenger buses, and it provides no aggregate base class, clock interface, event sourcing, business value objects or
public presentation of identifiers.

It is for developers who write a Symfony application as several contexts and want them to share a small, stable
vocabulary. The core stays deliberately tiny, because every addition is a dependency all contexts acquire at once,
and a breaking change costs every one of them a major version. Other phil/* bundles that need identifiers or domain
errors build on it, such as `phil/audit-bundle` and `phil/tenancy-bundle`. An application with a single context and
plain services has little use for it.

It is a Symfony bundle on Messenger and the Uid component, with Doctrine DBAL as an option. The design is ports and
adapters: the domain and application layers see only `CommandBus` and `QueryBus`, and the Messenger classes sit
behind them in the infrastructure layer.

## Status

| Part | State |
|---|---|
| `Identifier`, `DomainEvent`, `DomainError`, `InvalidValue` | Implemented and tested |
| `Command`, `Query`, `CommandBus`, `QueryBus`, `CommandHandler`, `QueryHandler` | Implemented and tested |
| `MessengerCommandBus`, `MessengerQueryBus`, `PhilDddBundle` (configuration `phil_ddd`) | Implemented and tested |
| `IdentifierType` (DBAL, optional) | Implemented; unit-tested on five platforms, integration-tested on MySQL 8 and PostgreSQL |
| Dispatching domain events after the Doctrine flush, inside the transaction | Not started |
| Documented Messenger inbox configuration | Not started |

## Documentation

- [Installation](doc/installation.md): requirements, installing, registering the bundle, and the two Messenger
  buses the application declares.
- [Configuration](doc/configuration.md): the `phil_ddd` keys with their defaults, the services and
  autoconfiguration rules the bundle registers, and the errors raised at build time.
- [Identifiers](doc/identifiers.md): `Identifier` and its UUIDv7 rules, why the caller generates it, and equality
  by class and value.
- [Domain events](doc/domain-events.md): the `DomainEvent` interface, its rules, and what is not implemented yet
  for dispatching events.
- [Domain errors](doc/errors.md): `DomainError` and `InvalidValue`, and how value objects validate themselves.
- [Command and query buses](doc/command-and-query-buses.md): commands, queries, handlers, why the markers decide
  the bus, how failures come out, and messages on a transport.
- [Doctrine identifier type](doc/doctrine-identifier-type.md): `IdentifierType`, the column per platform, the
  conversions and their errors.
- [Out of scope](doc/out-of-scope.md): what the bundle deliberately leaves out, and what is not started yet.
- [Extension points](doc/extension-points.md): every class and interface the application extends or implements,
  with its contract and an example.
- [Testing](doc/testing.md): the bundle's unit and integration suites, their environment variables, and how an
  application tests its handlers and wiring.
- [Reference](doc/reference.md): the public API by layer, one line per type.

## Quick start

```bash
composer require phil/ddd-bundle
```

```php
// config/bundles.php
Phil\DddBundle\PhilDddBundle::class => ['all' => true],
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware: [validation, doctrine_transaction]
            query.bus: ~
```

```php
final readonly class OrderId extends Identifier {}

final readonly class PlaceOrder implements Command
{
    public function __construct(public string $orderId) {}
}

final readonly class PlaceOrderHandler implements CommandHandler
{
    public function __invoke(PlaceOrder $command): void { /* … */ }
}

$orderId = OrderId::generate();
$commandBus->dispatch(new PlaceOrder($orderId->toString()));
```

## Requirements

PHP 8.5 or later; `symfony/config`, `symfony/dependency-injection`, `symfony/messenger` and `symfony/uid` 8.2 or
later; `doctrine/dbal` 4.2 or later only for the identifier column type.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
