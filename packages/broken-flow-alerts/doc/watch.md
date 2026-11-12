# The watch and its alerts

`BrokenFlowWatch::check()` runs the rule for each flow it is given and sends the alerts of the flows whose status
changed on the last whole UTC day.

```php
$watch = new BrokenFlowWatch($history, $sender, $clock);              // the default rule
$watch = new BrokenFlowWatch($history, $sender, $clock, new BrokenFlowRule(windowDays: 14));

$alerts = $watch->check([Flow::of('checkout', 'Ticket sales')]);      // list<FlowAlert>, already sent
```

## Flows

A `Flow` is an identifier the application's `CompletionHistory` understands and a label that an alert shows: 1 to 128
bytes each, without surrounding whitespace or control characters. The label is the identifier when none is given.

## The days read

The last whole day is the UTC day before the clock's instant: at 21:30 in Montréal on 11 November, already the 12th in
UTC, it is the 11th. For each flow the watch asks the history for `historyDays() + 1` days ending on that day — 36 with the
default rule —, from midnight UTC:

```php
$history->dailyCompletions($flow, new DateTimeImmutable('2026-10-07 00:00 UTC'), 36);   // 2026-10-07 to 2026-11-11
```

A history of another length is refused with `InvalidFlowValue`.

## One alert when the status changes

The watch keeps no record of past alerts. It evaluates the rule twice from the same history: once over the days ending on
the last whole day, once over the days ending the day before. Then:

| The day before | The last day | Alert |
|---|---|---|
| Not broken | Broken | `Broken`, with the stretch, the completions observed and expected |
| Broken | Not broken, and at least one completion on the last day | `Recovered`, with the completions of the last day |
| Broken | Not broken, and no completion on the last day | None: the silence has lasted long enough to become the rhythm |
| Broken | Broken | None: the flow was reported on the day it broke |
| Not broken | Not broken | None |

A flow is thus reported once when it breaks, and once when it completes again. **A run repeated on the same day sends the
same alerts again**: schedule one run a day.

## What an alert holds

| `FlowAlert` property | Meaning |
|---|---|
| `flow` | The `Flow` |
| `kind` | `FlowAlertKind::Broken` or `FlowAlertKind::Recovered` |
| `day` | The last whole day, `YYYY-MM-DD` |
| `since` | The first day of the stretch the numbers cover, `YYYY-MM-DD` |
| `observed` | The completions from `since` to `day` |
| `expected` | The completions the rhythm expects over the same days |

## Notifications

`Notifier\NotifierAlertSender` sends each alert as a Symfony Notifier `Notification`:

| Alert | Subject | Content | Importance |
|---|---|---|---|
| Broken | `Flow "Ticket sales" stopped completing` | `3 completions from 2026-11-08 to 2026-11-11, where about 42 were expected.` | high |
| Recovered | `Flow "Ticket sales" completes again` | `12 completions on 2026-11-11.` | low |

```php
new NotifierAlertSender($notifier);                                   // to the channels that need no recipient
new NotifierAlertSender($notifier, new Recipient('ops@example.com')); // to these recipients
```

The importance lets the application's channel policy send a broken flow to a chat room and a phone, and a recovery to a
chat room only. The text is in English; an application that wants its own wording, or a translation, writes its own
`AlertSender` ([extension-points.md](extension-points.md)).
