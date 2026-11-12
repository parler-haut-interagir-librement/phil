# Running

## Symfony Scheduler

With `symfony/scheduler`, the bundle declares the schedule `phil_transitions`: a `RunDueTransitions` message every
`frequency`, handled by `Scheduler::runDue($batch)`. A worker consumes it:

```bash
bin/console messenger:consume scheduler_phil_transitions
```

## Cron

Without a worker, run the command — it fails when a transition failed:

```bash
* * * * * bin/console phil:scheduled-transitions:run --limit=100
```

## Runs that overlap

A run claims each transition before applying it: one `UPDATE` sets `claimed_until`, `claim_for` seconds after the
claim is made — ten minutes by default —, only while the transition is pending and no other run holds it. A cron run
that lasts past the next minute, cron and a worker both configured, or two workers consuming the schedule, therefore
never apply the same transition twice: the second run skips what the first holds. Set `claim_for` longer than any
handler takes; a run that dies holding a transition lets another take it back once the claim lapses. A failed attempt
lets go of its claim at once, and is retried at the next run.

## The cost of a run

Each outcome is written by one `UPDATE`, which also lets go of the claim, and the transition is then detached from
the entity manager: no later flush of the run, the handlers' included, compares or writes it again. A run of a batch
costs a claim, the handler and one `UPDATE` per transition, whatever the size of the batch.

## When a handler breaks the entity manager

A handler whose own flush fails — a constraint violated — closes Doctrine's entity manager. The failed attempt is
still counted, written by the same plain `UPDATE` on the connection, so `max_attempts` gives it up in time; the run
then stops, and the transitions it did not reach wait, unclaimed, for the next run, which has a new entity manager —
the next cron run, or the worker's next message once the container is reset.
