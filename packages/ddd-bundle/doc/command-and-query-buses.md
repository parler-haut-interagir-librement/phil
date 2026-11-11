# Command and query buses

Six types in `Phil\DddBundle\Application\Bus`, and their two Messenger adapters in
`Phil\DddBundle\Infrastructure\Messenger`.

| Type | Kind | Role |
|---|---|---|
| `Command` | marker interface | An intention to change state |
| `Query` | marker interface, generic on its result (`@template TResult`) | A question asked of the read model |
| `CommandBus` | port | `dispatch(Command $command): void` |
| `QueryBus` | port | `ask(Query $query): mixed`, typed `TResult` for static analysis |
| `CommandHandler` | autoconfiguration marker | Tags the handler on the command bus |
| `QueryHandler` | autoconfiguration marker | Tags the handler on the query bus |
| `MessengerCommandBus` | adapter of `CommandBus` | Dispatches on the configured command bus |
| `MessengerQueryBus` | adapter of `QueryBus` | Dispatches on the configured query bus and returns the handler's result |

## Commands

```php
namespace App\Ordering\Application\Command;

use Phil\DddBundle\Application\Bus\Command;

final readonly class PlaceOrder implements Command
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        /** @var list<array{sku: string, quantity: int}> */
        public array $lines,
    ) {
    }
}
```

```php
namespace App\Ordering\Application\Command;

use Phil\DddBundle\Application\Bus\CommandHandler;

final readonly class PlaceOrderHandler implements CommandHandler
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function __invoke(PlaceOrder $command): void
    {
        $this->orders->add(Order::place(OrderId::fromString($command->orderId), /* … */));
    }
}
```

```php
$orderId = OrderId::generate();
$commandBus->dispatch(new PlaceOrder($orderId->toString(), $customerId, $lines));
// $orderId is already known: redirect to the order's page.
```

- **A command returns nothing.** The caller generates the identifier of what is created
  (`Identifier::generate()`) and passes it in the command.
- **A command handler has a single `__invoke` method and returns `void`.** Messenger reads the message class from
  the type of its parameter.
- **The command bus is the transactional one.** Its middleware, declared by the application, wraps the handler
  in a transaction (`doctrine_transaction` with Doctrine).

## Queries

```php
use Phil\DddBundle\Application\Bus\Query;

/** @implements Query<OrderSummary|null> */
final readonly class FindOrderSummary implements Query
{
    public function __construct(public string $orderId)
    {
    }
}
```

```php
use Doctrine\DBAL\Connection;
use Phil\DddBundle\Application\Bus\QueryHandler;

final readonly class FindOrderSummaryHandler implements QueryHandler
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(FindOrderSummary $query): ?OrderSummary
    {
        $row = $this->connection->fetchAssociative('SELECT … FROM orders WHERE id = ?', [/* … */]);

        return false === $row ? null : OrderSummary::fromRow($row);
    }
}
```

```php
$summary = $queryBus->ask(new FindOrderSummary($orderId)); // OrderSummary|null for PHPStan and Psalm
```

- **A query bypasses the aggregates.** It reads DTOs through DBAL or SQL. An aggregate is loaded to enforce an
  invariant, and a read enforces none.
- **One handler per query.** `ask()` returns the result of exactly one handler; zero or several handlers is a
  `LogicException` from Messenger.
- **The query bus is synchronous and not transactional.** Never route a query to a transport: `ask()` would find
  no result and fail.

## Why the markers decide the bus

With two buses and plain `#[AsMessageHandler]` handlers, a handler that names no bus is registered on every bus,
and a command can end up handled through the query bus, outside its transaction, without anything signalling
it. The bundle closes that door: every class that implements `CommandHandler` is tagged
`messenger.message_handler` with `bus: <command_bus>`, every `QueryHandler` with `bus: <query_bus>`. The marker
is the single place where the choice is made, and a reviewer sees it in the class declaration.

Do not add `#[AsMessageHandler]` to a class that already implements a marker: the attribute would register a
second handler definition with its own options.

## How failures come out

| Bus | Failure in the handler | What the caller receives |
|---|---|---|
| Command | A `DomainError` | The `DomainError` itself, unwrapped |
| Command | Several handlers fail, one of them with a `DomainError` | The first `DomainError` among the wrapped exceptions |
| Command | Anything else | Messenger's `HandlerFailedException`, unchanged |
| Query | Anything | The exception the handler threw, unwrapped, whatever its type |

The asymmetry is deliberate. A command may have several handlers and several things may fail: only the domain
error means something to the caller, and unwrapping any other failure would pass it off as an invariant refusal.
A query has exactly one handler, so its one failure comes out as is, and the caller catches a "not found" without
knowing Messenger.

## Messages on a transport

Commands may be routed to an asynchronous transport. Two consequences:

- **Use the JSON serializer.** PHP's native serialization (`unserialize()`), Messenger's default, is an object
  injection surface. Messages are therefore plain DTOs carrying scalars and arrays, never Doctrine entities,
  resources or anything whose reconstruction depends on the state of the container. In the examples, identifiers
  travel as strings and become `Identifier` objects inside the handler.
- **An asynchronous command fails in the worker, not in the caller.** `dispatch()` returns as soon as the message
  is sent, and a `DomainError` raised later is handled by Messenger's retry and failure transports.

## Using the adapters without the bundle

Both adapters are plain classes over a `Symfony\Component\Messenger\MessageBusInterface`:

```php
$commandBus = new MessengerCommandBus($messengerCommandBus);
$queryBus = new MessengerQueryBus($messengerQueryBus);
```
