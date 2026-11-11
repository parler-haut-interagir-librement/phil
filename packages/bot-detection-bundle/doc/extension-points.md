# Extension points

The bundle has **one port**: `Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter`. It declares no service
id and no tag for it; the application registers one adapter instance per surface and passes it to the
`VerdictHandler` of that surface ([configuration.md](configuration.md)).

## `RobotVerdictCounter`

```php
namespace Phil\BotDetectionBundle\Domain\Counter;

interface RobotVerdictCounter
{
    public function increment(): void;
}
```

### Contract

| Method | Contract |
|---|---|
| `increment(): void` | Adds one robot verdict to the aggregate count of the surface this instance is wired for. Called by `VerdictHandler` for `Verdict::Robot` only, once per verdict |

### Invariants

- **Nothing about a person enters the counter.** The method takes no argument by design; an adapter must not
  reach for the current request, session, user or client address to enrich the count either.
- **One instance per surface.** A per-surface or per-site count is a separate instance, never an argument.
- **Windows are the adapter's.** Bucketing increments into time windows (per minute, per hour) is how a rate is
  read; the window key is a time, never a person.
- **The count is reported outside per-person data**: an alerting dashboard, a metrics gauge, or a client that
  pushes one number per window to another service.
- **Failure must not refuse the request.** A verdict never refuses (see [friction.md](friction.md)); a counter
  backend that is down should lose the increment rather than fail the request that carried it.

### Example adapters

A counter bucketed per minute in a PSR-6/Symfony cache pool (e.g. Valkey or Redis):

```php
use Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

final readonly class CacheRobotVerdictCounter implements RobotVerdictCounter
{
    public function __construct(
        private string $surface,              // e.g. 'bot.robot_verdicts.sign_up'
        private AdapterInterface $pool,
        private ClockInterface $clock,
    ) {}

    public function increment(): void
    {
        try {
            $item = $this->pool->getItem($this->surface.'.'.$this->clock->now()->format('YmdHi'));
            $item->set(((int) $item->get()) + 1)->expiresAfter(86400);
            $this->pool->save($item);
        } catch (\Throwable) {
            // lose one increment rather than fail the request
        }
    }
}
```

(A cache read-then-write is not atomic; under load, prefer an atomic `INCR` on the backend.)

A counter for tests, as the bundle's own tests use:

```php
final class InMemoryRobotVerdictCounter implements RobotVerdictCounter
{
    public int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }
}
```

## What is not an extension point

- `FrictionPolicy` is `final` with a private constructor: the two named constructors `robotOnly()` and
  `robotOrUnknown()` are the only policies.
- `Friction` and `Verdict` are enums; their cases are fixed. There is no refusal case to add.
- `VerdictHandler` is `final`; compose it rather than extend it.
