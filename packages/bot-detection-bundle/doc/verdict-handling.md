# Handling a verdict, and the robot-verdict counter

State: `VerdictHandler` is implemented and tested; `RobotVerdictCounter` is a port implemented by the
application ([extension-points.md](extension-points.md)).

## `VerdictHandler`

`Phil\BotDetectionBundle\Application\VerdictHandler` decides one request from its verdict:

```php
final readonly class VerdictHandler
{
    public function __construct(FrictionPolicy $policy, RobotVerdictCounter $counter);

    public function handle(Verdict $verdict): Friction;
}
```

`handle()`:

1. If the verdict is `Robot`, increments the counter once.
2. Returns `$policy->frictionFor($verdict)`.

```php
$friction = $this->signUpVerdicts->handle(Verdict::fromSubmitted($request->request->get('verdict')));

if ($friction->adds()) {
    $this->signUpLimiter->addBackoffStep($throttleKey); // your meaning of one step on this surface
}
// The verdict goes no further: not logged, not in the session, not stored.
```

**The caller's obligations:** apply the friction to the request, then drop the verdict, without writing it to the
request log, the session or anything keyed on a person.

| Verdict | Policy | Counter | Returned |
|---|---|---|---|
| `Robot` | any | +1 | `Step` |
| `Human` | any | unchanged | `None` |
| `Unknown` | `robotOnly()` | unchanged | `None` |
| `Unknown` | `robotOrUnknown()` | **unchanged** | `Step` |

## `RobotVerdictCounter`

`Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` is the aggregate count of robot verdicts, and **the
only trace a verdict leaves**.

```php
interface RobotVerdictCounter
{
    public function increment(): void;
}
```

### Why `increment()` takes no argument

No account, session, IP address, user agent, order or request id can reach the counter, because the port has
nowhere to put one. A counter that needs to be split is split without a person in it:

- **by surface**: the application wires one counter per surface (sign-up, payment, a measured site);
- **by time window**: the adapter buckets increments into windows.

### Why it counts robot verdicts and not friction steps

A missing or malformed verdict is not counted, **even when `FrictionPolicy::robotOrUnknown()` adds a step for
it**. A detector that goes silent (a broken deploy, a blocked script) then shows as a count that drops, instead of
being masked by verdicts that never arrived. Watch the count against its ordinary rate, and alert both on a surge
and on a window with no robot verdict at all on a surface that usually has some.

An application that also wants the number of steps added counts them in the limiter that absorbs them.
