# Domain events

`Phil\DddBundle\Domain\Event\DomainEvent` is the interface of a past fact inside one bounded context.

```php
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
```

## Declaring an event

```php
namespace App\Ordering\Domain\Event;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Event\DomainEvent;

final readonly class OrderPlaced implements DomainEvent
{
    public function __construct(
        public string $orderId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

Take the time from a `Psr\Clock\ClockInterface` injected where the event is raised, never from `new
DateTimeImmutable()`, so that tests control it. The bundle ships no clock interface of its own (see
[out-of-scope.md](out-of-scope.md)).

## Rules

- **A domain event is not an integration event.** It stays inside the context that raised it. What crosses a
  context boundary goes through the published contracts of the emitting context and an anti-corruption layer
  on the consumer side; the bundle defines neither.
- **Events notify, they do not reconstitute.** State is stored as state. There is no event store and no replay:
  an aggregate is never rebuilt from its events.
- **An event is a name and a time; the rest is the context's.** The interface asks only for `occurredAt()`, so
  that each context designs its events' payloads.

## What is implemented, and what is not

| Part | State |
|---|---|
| `DomainEvent` interface | Implemented |
| Recording events on an aggregate (an interface the aggregate implements) | **Not started** |
| Dispatching recorded events after the Doctrine flush, inside the handler's transaction and before its commit | **Not started** |
| A documented Messenger inbox configuration for the consumers of integration events | **Not started** |

Until the dispatch exists, an application that needs it records events on its aggregates and dispatches them
itself, on a bus of its own. Two constraints are worth knowing when doing so:

- Dispatch inside the transaction, after the flush and before the commit, so that an event is never seen for a
  write that rolled back.
- Do not rely on Messenger's `DispatchAfterCurrentBusStamp` for this: it moves the dispatch outside the handler's
  transaction, and Symfony 8.2 refuses that stamp on a transport with an inbox unless the message goes through an
  outbox.

A recording interface will be an interface rather than a trait: a shared trait holding a private list of events
would be an aggregate base class under another name.
