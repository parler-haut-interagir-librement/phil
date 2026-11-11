# Circuits

## Keys

A `CircuitKey` names what one breaker guards: an HTTP client (`http.payments`), or a destination such as one webhook
endpoint (`webhook.partner-a`). One circuit per key, so that one failing third party or one slow destination only ever
trips its own.

A key is lowercase ASCII segments separated by `.`, `_` or `-`, at most 87 bytes. The length leaves room for an
adapter to derive a name of its own from a key, such as a feature flag of at most 100 bytes named `circuit-open.<key>`.
Anything else throws `InvalidCircuit`.

```php
CircuitKey::of('http.payments');            // kept
CircuitKey::of('https://example.com/hook'); // InvalidCircuit
```

## Policies

`CircuitPolicy::of($failureRatePercent, $minimumCalls, $windowSeconds, $openSeconds, $halfOpenSuccesses = 1,
$slowCallMilliseconds = null, $slowCallRatePercent = 100)`:

| Value | Bounds | Meaning |
|---|---|---|
| `failureRatePercent` | 1 to 100 | The share of failed calls that opens the circuit. |
| `minimumCalls` | at least 1 | The calls a window needs before it can open the circuit. |
| `windowSeconds` | at least 1 | The length of the fixed window the calls are counted in. |
| `openSeconds` | at least 1 | How long the circuit stays open before it lets trial calls through. |
| `halfOpenSuccesses` | at least 1 | The consecutive trial successes that close it. |
| `slowCallMilliseconds` | at least 1, or `null` | The duration a slow call exceeds; `null` counts no call as slow. |
| `slowCallRatePercent` | 1 to 100 | The share of slow calls that opens the circuit, when there is a threshold. |

A value out of bounds throws `InvalidCircuit`. The values are the application's, one policy per third party.

- **The minimum is the denominator.** Without it, one failure out of one call is a 100 % failure rate and an
  isolated error trips a healthy third party. `trips($calls, $failures)` is integer arithmetic
  (`failures × 100 ≥ rate × calls`), so 1 failure out of 3 calls against 33 % is decided exactly: it trips.
- **Slow calls count too.** A third party that answers, but too slowly, ties up the workers waiting for it as surely
  as one that fails. With a threshold, a call that lasts longer than `slowCallMilliseconds` is slow, whether it
  succeeded or failed — a call of exactly the threshold is not —, and `trips($calls, $failures, $slowCalls)` also
  opens the circuit when `slowCalls × 100 ≥ slowCallRatePercent × calls`, over the same minimum of calls. A call
  whose duration was not measured is never slow; `isSlow(CallOutcome)` says whether one is.

## The state machine

| State | Calls | Leaves when |
|---|---|---|
| `closed` | Pass, and their outcomes are counted in the current window. | It **opens** when, within the window, at least `minimumCalls` calls were made and at least `failureRatePercent` % of them failed, or at least `slowCallRatePercent` % of them were slow. |
| `open` | Go straight to the fallback, without waiting for a timeout. | After `openSeconds`, it turns **half-open**. |
| `half_open` | Trial calls pass. | `halfOpenSuccesses` consecutive successes that are not slow **close** it; a single failure, or a single slow call, **opens** it again for a full `openSeconds`. |

`CircuitSnapshot` holds a state, the instant it began (`since`) and its counters. Each transition returns a new
snapshot and none reads a clock: time enters as an argument.

| Method | Effect |
|---|---|
| `closed(DateTimeImmutable $now)` | A closed circuit whose window starts now. |
| `restore($state, $since, $calls, $failures, $halfOpenSuccesses, $slowCalls = 0)` | A stored snapshot rebuilt, the counters clamped to what they can be. |
| `at(CircuitPolicy $policy, DateTimeImmutable $now)` | The transitions only time causes: open to half-open after `openSeconds`, a fresh window after `windowSeconds`. Returns the same snapshot when nothing changed. |
| `record($policy, CallOutcome $outcome, $now)` | A call counted, after `at()`: a failure, a slow call, or both. Only a failure or a slow call opens the circuit. |
| `recordSuccess($policy, $now)` | A success whose duration was not measured, after `at()`. |
| `recordFailure($policy, $now)` | A failure whose duration was not measured, after `at()`. |
| `permitsCalls()` | Whether calls pass: every state but `open`. |

- **A fixed window.** A closed circuit starts a fresh window, counters at zero, `windowSeconds` after the window
  began. A sliding window, or a limit on concurrent trial calls in half-open, needs shared storage and a lock, and
  belongs to the store adapter.
- An outcome recorded while open — a call that raced with another process — changes nothing.
- Every new state starts with empty counters.

## Outcomes

A `CallOutcome` is how one call went: `CallOutcome::succeeded($milliseconds = null)` or
`CallOutcome::failed($milliseconds = null)`, its public `failed` and `milliseconds`. The duration is the caller's
measure, at least 0, or `null` when it was not measured; a negative one throws `InvalidCircuit`.
