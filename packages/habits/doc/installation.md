# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `mbstring` | The name of a habit counted in characters. |
| `psr/clock` | `^1.0` | Today, in the member's time zone, and the instant of a check-in. |
| `psr/event-dispatcher` | `^1.0` | The events after a check-in. |
| `doctrine/dbal` | `^4.5`, suggested | `DbalHabitStore` and `DbalCheckInStore`, the stores in a database. |

```sh
composer require phil/habits
```

## The services

`Habits` takes a store of habits, a store of check-ins, a clock and, optionally, an event dispatcher, a
`ProgressCalculator`, the `Milestones` to celebrate and the number of days a check-in may go back — seven by default,
up to 366. In a Symfony application with Doctrine:

```yaml
# config/services.yaml
services:
    Phil\Habits\Dbal\DbalHabitStore: ~
    Phil\Habits\Dbal\DbalCheckInStore: ~
    Phil\Habits\Contract\HabitStore: '@Phil\Habits\Dbal\DbalHabitStore'
    Phil\Habits\Contract\CheckInStore: '@Phil\Habits\Dbal\DbalCheckInStore'

    Phil\Habits\Milestones:
        arguments: { $streaks: [7, 30, 100], $strengths: [50, 80] }

    Phil\Habits\Habits:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'
            $backfillDays: 3
```

The tables of the database stores are added to a schema by their `configureSchema()`: see [Storage](storage.md). The
stores in memory, `InMemoryHabitStore` and `InMemoryCheckInStore`, serve tests and demonstrations.
