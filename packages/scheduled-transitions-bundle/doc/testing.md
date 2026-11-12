# Testing

| Area | What is covered |
|---|---|
| `ScheduledTransition`, `ResourceReference`, the exceptions | Every check; times in UTC; moves, cancels, attempts; the mapping |
| `Scheduler` with `DoctrineScheduledTransitionRepository` | On SQLite in memory: scheduling and moving, runs, retries and giving up, cancels, batches, the soonest first |
| `RunTransitionsCommand`, `TransitionsSchedule`, `RunDueTransitionsHandler` | Limits; the recurring message and its trigger |
| `WorkflowTransitionHandler` | A workflow's transition applied and saved; a transition not allowed; no workflows |
| `PhilScheduledTransitionsBundle` | Services, settings, the mapping |

A functional suite boots a kernel with FrameworkBundle and a `page` workflow, DoctrineBundle on SQLite and the bundle:
a page is published and unpublished as a moved clock reaches each moment.

```sh
vendor/bin/phpunit packages/scheduled-transitions-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/scheduled-transitions-bundle/tests/Benchmark
```
