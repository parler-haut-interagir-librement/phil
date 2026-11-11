# Extension points

The bundle has no port the application must implement before it works. What the application writes against it
are subclasses, implementations of marker interfaces, and, if it wants, its own adapters of the two bus ports.

| Extension point | Kind | Who implements it | Service id or tag |
|---|---|---|---|
| `Identifier` | abstract class | one empty `final readonly` subclass per aggregate | none |
| `IdentifierType` | abstract DBAL type | one subclass per identifier class | a DBAL type name chosen by the application |
| `DomainEvent` | interface | each event of a context | none |
| `DomainError` | interface | each domain exception of a context | none |
| `Command`, `Query` | marker interfaces | each message | none |
| `CommandHandler`, `QueryHandler` | autoconfiguration markers | each handler | tag `messenger.message_handler` with the configured bus |
| `CommandBus`, `QueryBus` | ports | `MessengerCommandBus` and `MessengerQueryBus` by default; an application may decorate or replace them | aliases `Phil\DddBundle\Application\Bus\CommandBus` and `…\QueryBus` |

## `Identifier`

- Contract: none to write. Declare `final readonly class XId extends Identifier {}`.
- Invariants: the constructor is `final private`, so a subclass cannot add state or bypass validation.
- See [identifiers.md](identifiers.md).

## `IdentifierType`

- Contract: `protected function identifierClass(): string`, returning the `class-string` of exactly one
  `Identifier` subclass. Return a constant: it is called on every conversion.
- Invariants: one type per identifier class; never register two type names for the same subclass in one schema
  unless both columns really hold that identifier.
- See [doctrine-identifier-type.md](doctrine-identifier-type.md).

## `DomainEvent`

- Contract: `occurredAt(): DateTimeImmutable`, the time the fact happened, taken from an injected clock.
- Invariants: immutable; carries scalars and identifiers; never an integration event.
- See [domain-events.md](domain-events.md).

## `DomainError`

- Contract: none beyond `Throwable`. Extend whichever SPL exception fits (`DomainException`, `RuntimeException`…)
  and implement the interface.
- Invariants: throw it only for refusals the caller can act upon; infrastructure failures are not domain errors,
  or the command bus would unwrap them as if they were.
- See [errors.md](errors.md).

## `Command`, `Query`

- Contract: none. A `Query` declares its result type with `@implements Query<ResultType>`.
- Invariants: plain DTOs with scalar and array properties, safe for the JSON serializer.

## `CommandHandler`, `QueryHandler`

- Contract: a single public `__invoke(TheMessage $message)`; `void` for a command handler, the declared result for
  a query handler.
- Invariants: one marker per class, never both; no `#[AsMessageHandler]` on top of the marker; exactly one
  handler per query.
- The application's services must be autoconfigured.

```php
final readonly class CancelOrderHandler implements CommandHandler
{
    public function __construct(private OrderRepository $orders, private ClockInterface $clock)
    {
    }

    public function __invoke(CancelOrder $command): void
    {
        $order = $this->orders->get(OrderId::fromString($command->orderId));
        $order->cancel($this->clock->now()); // may throw a DomainError
    }
}
```

## `CommandBus`, `QueryBus`

- `CommandBus::dispatch(Command $command): void`. Contract: hand the command to its handler; let a `DomainError`
  out as thrown; return nothing.
- `QueryBus::ask(Query $query): mixed`. Contract: return the result of the query's one handler; let its failure
  out as thrown.
- An application can decorate the aliases with Symfony's service decoration, for logging or tracing:

```php
use Phil\DddBundle\Application\Bus\Command;
use Phil\DddBundle\Application\Bus\CommandBus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(CommandBus::class)]
final readonly class LoggingCommandBus implements CommandBus
{
    public function __construct(
        #[AutowireDecorated] private CommandBus $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $this->logger->info('Dispatching {command}', ['command' => $command::class]);
        $this->inner->dispatch($command);
    }
}
```

- In a unit test, an in-memory implementation of either port is a few lines; see [testing.md](testing.md).
