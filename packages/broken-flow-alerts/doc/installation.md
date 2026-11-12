# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `psr/clock` | The last whole UTC day, from the application's clock |
| `symfony/notifier` 8.2, optional | `Notifier\NotifierAlertSender`, the alerts as notifications |

## Installing

The library is in `0.1.x-dev` and is not yet published on Packagist. Until it is, declare a `path` repository to a local
checkout, or a `vcs` repository, then require it:

```bash
composer require phil/broken-flow-alerts:^0.1@dev
```

It is a library, not a bundle: there is nothing to register. Build one `BrokenFlowWatch` with the application's
`CompletionHistory`, an `AlertSender` and a PSR-20 clock — Symfony's `clock` service, or any other —, and keep it; it
holds no state of its own, and serves any number of runs in a long-running worker.

## In a Symfony application

With `phil/analytics-bundle`, the bundle wires everything from its configuration, the goals of its sites as flows. Without
it, declare the services:

```yaml
services:
    Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender:
        arguments: ['@notifier']

    Phil\BrokenFlowAlerts\BrokenFlowRule:
        arguments: { $baselineDays: 28, $windowDays: 7, $significance: 0.001 }

    Phil\BrokenFlowAlerts\BrokenFlowWatch:
        arguments:
            - '@App\Monitoring\OrderCompletionHistory'           # your CompletionHistory
            - '@Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender'
            - '@clock'
            - '@Phil\BrokenFlowAlerts\BrokenFlowRule'
```

Then run `check()` once a day, after midnight UTC, from a scheduled message or a cron job. Running it more often sends the
same alerts again on the same day.
