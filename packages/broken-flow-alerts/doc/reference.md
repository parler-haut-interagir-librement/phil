# API reference

Every public type of the library, with its role. Namespaces are relative to `Phil\BrokenFlowAlerts`.

| Type | Role |
|---|---|
| `Flow` | `of(string $id, ?string $label)`; `id`, `label`; `MAX_LENGTH` = 128 |
| `BrokenFlowRule` | `evaluate(list<int>): FlowStatus`, `historyDays()`; `baselineDays`, `windowDays`, `significance`, `weekly`; `DEFAULT_*`, `MAX_BASELINE_DAYS`, `MAX_WINDOW_DAYS` ([detection.md](detection.md)) |
| `FlowStatus` | `broken`, `days`, `observed`, `expected`, `probability` |
| `BrokenFlowWatch` | `check(iterable<Flow>): list<FlowAlert>`, the alerts of the last whole UTC day, sent ([watch.md](watch.md)) |
| `FlowAlert` | `flow`, `kind`, `day`, `since`, `observed`, `expected` |
| `FlowAlertKind` | `Broken`, `Recovered` |
| `Contract\CompletionHistory` | `dailyCompletions(Flow, DateTimeImmutable, int): list<int>` ([extension-points.md](extension-points.md#completionhistory)) |
| `Contract\AlertSender` | `send(FlowAlert)` ([extension-points.md](extension-points.md#alertsender)) |
| `Notifier\NotifierAlertSender` | An `AlertSender` over Symfony Notifier; `notificationOf(FlowAlert): Notification` |
| `Exception\BrokenFlowAlertsException` | Implemented by every exception of the library |
| `Exception\InvalidFlowValue` | A refused flow, setting or history; `because()` |
| `Internal\PoissonTail` | `atMost(int, float)`, internal; `EXACT_UP_TO` = 10,000 |
