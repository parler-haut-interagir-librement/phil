# Runs

## From an event to a run

`AutomationEngine::handle($event)` asks each trigger that reads the event's class to match it against the options of
each enabled definition of its type. A match starts a run, whose id is drawn from the automation and the event key:
the store creates it once, so the same event delivered twice starts it once. Each new run is saved as waiting, due at
once, and an `AdvanceRun` message carrying its id is sent.

## Going through the steps

`AdvanceRunHandler` calls `AutomationEngine::advance()` on a worker. The engine reads the run and its definition's
version, then claims it: it saves the run as running, with a lease of five minutes, over the revision it read. Of two
workers that read the same revision, one saves and the other changes nothing and stops. Then:

| Step | The engine |
|---|---|
| Action | Runs it, then saves the result and the next step, with the lease renewed |
| Delay | Saves the run as waiting until the delay is over, and sends an `AdvanceRun` with a `DelayStamp` |
| Filter | Goes on when the condition holds; otherwise ends the run as `filtered` |
| Branch | Goes on with the first step of `then` or `else`, or after the branch when that path is empty |

A run with no step left ends as `completed`. A message that arrives before its time, or for a run another worker holds,
changes nothing.

## Messages and the schedule

`AdvanceRun` and `ResumeDueRuns` carry an identifier and a number, nothing else. A transport may not keep a delayed
message for weeks, and a worker may stop in the middle of a run: `AutomationsSchedule`, the schedule `automations` of
Symfony Scheduler, sends a `ResumeDueRuns` every minute, and its handler sends an `AdvanceRun` for each run whose time
has come — a delay over, a retry due, a lease ended.

```php
new AutomationsSchedule(frequency: '30 seconds', limit: 1000);
```

## Retries

An action that throws `ActionFailed::retryable()`, or any exception but a permanent `ActionFailed`, is tried again when
its step allows retries: the run waits 30 seconds, then 60, 120… up to an hour between attempts, and every attempt
gets the same idempotency key.

## Compensation

When a step fails for good — a permanent failure, retries exhausted, a condition that fails, an action no longer
registered —, the engine goes back through the action steps done, newest first, and calls `compensate()` on those whose
action implements `CompensableAction`, with the result each returned. The run ends as `failed`, its reason kept. When a
compensation throws, the others still run, and the run ends as `compensation_failed`: someone has to look.

## States

| `RunState` | Means |
|---|---|
| `waiting` | It goes on at its time |
| `running` | A worker holds it until its lease ends |
| `completed` | Every step ran |
| `filtered` | A filter ended it |
| `failed` | A step failed; what could be undone was |
| `compensation_failed` | A step failed, and undoing an earlier one failed too |

## Workers

The engine, the catalogue, the evaluator and the stores keep nothing between calls, so one instance serves every
message of a long-running worker. The condition cache is bounded at 256 entries. `WebhookAction` keeps the addresses
its guard resolved: it implements Symfony's `ResetInterface`, which the container calls between messages.

The engine logs a failed run with its id and its automation's id only.
