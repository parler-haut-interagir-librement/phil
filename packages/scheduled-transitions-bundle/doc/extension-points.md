# Extension points

What an application implements, configures or relies on. Resources are `phil/resource` references.

## Ports

### `TransitionHandler`

`Phil\ScheduledTransitionsBundle\Application\TransitionHandler` — applies a planned transition. A service implementing
it is tagged `phil_scheduled_transitions.handler` by autoconfiguration; the first handler that supports a transition
applies it, and `before` or `after` in the tag orders the application's against `WorkflowTransitionHandler`. See
[Handlers](handlers.md).

| Method | Contract |
|---|---|
| `supports(ResourceReference $resource, string $transition): bool` | Whether it applies this transition to this resource |
| `apply(ResourceReference $resource, string $transition): void` | Applies it and saves; throws when it cannot, and the attempt is counted and retried up to `max_attempts` |

A run that outlives `claim_for` lets another run take the transition back: a handler is written so that applying a
transition already applied leaves the resource as it is.

### `SubjectResolver`

`Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver` — with `symfony/workflow`, finds the subject
the workflow moves. A service implementing it is tagged `phil_scheduled_transitions.subject_resolver` by
autoconfiguration.

| Method | Contract |
|---|---|
| `resolve(ResourceReference $resource): ?object` | The subject, or `null` for a reference it does not know |
| `saved(object $subject): void` | Saves the subject once moved |

### `ScheduledTransitionRepository`

`Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository` — the planned transitions, a public
alias of `DoctrineScheduledTransitionRepository`. A store of the application's own keeps `claim()` atomic, so that two
runs never apply one transition at once, and `due()` bounded by its limit.

## Configuration

| Option | What it extends |
|---|---|
| `frequency` | How often Symfony Scheduler applies the transitions that are due |
| `batch` | How many a run applies at most |
| `max_attempts` | How many times a failing transition is tried |
| `claim_for` | How long a run holds a transition it applies |

## Running

`phil:scheduled-transitions:run` from a cron, or the schedule `TransitionsSchedule` registers with Symfony Scheduler —
see [Running](running.md). `Scheduler` is the service the application plans transitions with — see
[Scheduling](scheduling.md).
