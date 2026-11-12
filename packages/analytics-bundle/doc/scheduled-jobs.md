# Scheduled jobs

Four jobs run once a day, each when its block is enabled: the retention of the counters, the reports due that day,
the check of the watched flows, and the check of the traffic of the watched sites. They run on the Symfony Scheduler schedule `phil_analytics`:

```bash
bin/console messenger:consume scheduler_phil_analytics
```

| Message | Handler | Does |
|---|---|---|
| `Application\Message\ApplyCounterRetention` | `ApplyCounterRetentionHandler` | Rolls the daily counters past their retention into monthly totals |
| `Application\Message\SendDueReports` | `SendDueReportsHandler` | Sends the weekly reports on a Monday and the monthly ones on the first |
| `Application\Message\CheckBrokenFlows` | `CheckBrokenFlowsHandler` | Alerts when a watched goal stops being reached, or is reached again |
| `Application\Message\CheckTrafficAlerts` | `CheckTrafficAlertsHandler` | Alerts when a watched site's visits of the day before spike or dip |

**The messages carry nothing.** Each handler reads the UTC day from the `clock` service when it runs, so a message
delayed past midnight works on the day it is handled, and nothing personal ever travels through a transport.

**Once a day, at `schedule.time` in UTC** (`05:00` by default): late enough that the day before is whole everywhere the
counters are written, early enough for a report read in the morning. With `schedule.enabled: false`, or without
`symfony/scheduler`, no schedule is registered; the handlers stay, and the application dispatches the messages on its own
schedule.

## Retention

`CounterRetentionRun::runFor($today)` asks `CounterRollup::rollUpBefore()` to roll up the days before
`CounterRetention::firstDailyDay($today)` — the first of the month 13 months back, or `retention.months` back —, with
the configured small-number threshold for open dimensions. The bound moves on the first of each month, so the other days
roll up nothing ([retention.md](retention.md)).

The job is wired when `retention.enabled` is true and the counter store has a rollup: the `dbal` store, or a store of the
application with `counters.rollup`.

## Reports

`DueReports::sendFor($today)` sends nothing on most days. On a Monday, every subscription of the weekly cadence receives
the last whole week, Monday to Sunday; on the first of a month, every subscription of the monthly cadence receives the
last whole month. A Monday that is a first sends both.

```yaml
phil_analytics:
    reports:
        enabled: true
        subscriptions: App\Analytics\DbalReportSubscriptions   # ReportSubscriptions
        sender: App\Analytics\MailerReportSender               # ReportSender
```

Who receives what and how it is written stay the application's ([sharing-reports-alerts.md](sharing-reports-alerts.md)).

## Broken flows

A goal that stops being reached is often a broken page: a payment provider that changed its answer, a form whose
button no longer submits, a redirect that loops. With `phil/broken-flow-alerts`, the bundle watches the goals it is
given and alerts when one is reached far less than its rhythm leads to expect:

```yaml
phil_analytics:
    broken_flows:
        enabled: true
        flows:
            checkout: { site: spring-con, goal: ticket, label: 'Ticket sales' }
            contact: { site: spring-con, goal: contact }
```

**Passive only.** The check reads the goal counters the collection already keeps (`goals`, dimension `goal`), through
`GoalCompletionHistory`; it sends no request to the site, synthetic or otherwise.

Each flow's last 35 days are read, day by day: the 28 days of the baseline set the rhythm, weekday by weekday, and the
last 7 days are compared with it as a Poisson count. A busy flow is broken after one silent day; a flow reached twice a
day needs about four; a flow whose usual day is close to zero is never broken, since a silent day is ordinary there. The
alert goes out on the day the flow breaks, and a second one when it is reached again. The rule and its settings are
described in the documentation of `phil/broken-flow-alerts`.

| Key | Default | Meaning |
|---|---|---|
| `flows` | none | The goals watched, by flow identifier: `site`, `goal`, and the `label` an alert shows |
| `baseline_days` | `28` | The days that set the rhythm, 7 to 366 |
| `window_days` | `7` | The last days compared with it, 1 to 31 |
| `significance` | `0.001` | How unlikely the drop must be in a working flow, above 0 and under 0.5 |
| `weekly` | `true` | Whether each weekday has its own rhythm, from a baseline of two weeks or more |
| `sender` | none | The id of an `AlertSender`; without one, `NotifierAlertSender` sends through the `notifier` service |

Without a sender, the alerts go through Symfony Notifier as notifications without recipients: high importance for a
broken flow, low for a recovery, so the channel policy of `framework.notifier` decides where each goes (a chat room,
for instance). An application that wants recipients or its own wording names its own `AlertSender`.

## Traffic alerts

`TrafficAlertRun::checkFor($today)` reads, for each watched site, the visits of the day before and of the
`baseline_days` before it, and hands each alert of `TrafficAlertRule` to the `TrafficAlertNotifier` the application
names. A site whose visits fall to nothing is a dip, so a collection that stopped shows the next morning instead of
looking like a quiet week.

```yaml
phil_analytics:
    traffic_alerts:
        enabled: true
        sites: [spring-con]
        notifier: App\Analytics\ChatTrafficAlertNotifier      # TrafficAlertNotifier
```

| Key | Default | Meaning |
|---|---|---|
| `sites` | none | The references of the watched sites; at least one |
| `notifier` | none | The id of a `TrafficAlertNotifier`; required |
| `spike_percent` | `300` | A day at or above this share of the usual day is a spike; above 100 |
| `dip_percent` | `30` | A day at or below this share of the usual day is a dip; 1 to 99 |
| `minimum_baseline` | `20` | A site whose usual day is under this many visits raises no alert |
| `baseline_days` | `28` | The days the usual day is the mean of, 7 to 366 |

The run reads the counters and writes nothing; its work is the watched sites times the baseline days, one read per day.
