# Reference

Every public type of the bundle, by layer. All are implemented and tested unless the state says otherwise.

## `Phil\DddBundle\Domain`

Depends on `symfony/uid` only.

| Type | Kind | Role | State |
|---|---|---|---|
| `Identifier\Identifier` | `abstract readonly class`, `Stringable` | Base of aggregate identifiers: UUIDv7, `generate()`, `fromString()`, `equals()` by class and value, `toString()` | Implemented |
| `Event\DomainEvent` | interface | A past fact inside a context: `occurredAt()` | Implemented |
| `Exception\DomainError` | interface, extends `Throwable` | What the domain throws | Implemented |
| `Exception\InvalidValue` | `final class`, `InvalidArgumentException` and `DomainError` | A value object refused its construction: `because(string $reason)` | Implemented |

## `Phil\DddBundle\Application`

| Type | Kind | Role | State |
|---|---|---|---|
| `Bus\Command` | marker interface | An intention to change state; returns nothing | Implemented |
| `Bus\Query` | marker interface, `@template TResult` | A question asked of the read model | Implemented |
| `Bus\CommandBus` | port | `dispatch(Command): void`, throws `DomainError` | Implemented |
| `Bus\QueryBus` | port | `ask(Query): mixed`, typed `TResult` | Implemented |
| `Bus\CommandHandler` | autoconfiguration marker | Tags the handler on the command bus | Implemented |
| `Bus\QueryHandler` | autoconfiguration marker | Tags the handler on the query bus | Implemented |

## `Phil\DddBundle\Infrastructure`

| Type | Kind | Role | State |
|---|---|---|---|
| `Messenger\MessengerCommandBus` | `final readonly class`, `CommandBus` | Dispatches on the configured Messenger bus; unwraps the first `DomainError` | Implemented |
| `Messenger\MessengerQueryBus` | `final class`, `QueryBus` | Dispatches on the configured Messenger bus; returns the one handler's result; unwraps any handler failure | Implemented |
| `Doctrine\IdentifierType` | `abstract class`, DBAL `Type`, `@template T of Identifier` | Column type of one identifier class: native UUID or 16-byte binary | Implemented; optional, needs `doctrine/dbal` 4.2 or later |

## The bundle

| Type | Kind | Role | State |
|---|---|---|---|
| `PhilDddBundle` | `final class`, `AbstractBundle` | Configuration alias `phil_ddd`; wires the two adapters, their aliases and the marker autoconfiguration | Implemented |

## Not in the code yet

| Part | State |
|---|---|
| Recording domain events on aggregates and dispatching them after the flush, inside the transaction | Not started |
| Documented Messenger inbox configuration for consumers of integration events | Not started |

See [configuration.md](configuration.md) for the services and tags, and [out-of-scope.md](out-of-scope.md) for
what the bundle will not contain.
