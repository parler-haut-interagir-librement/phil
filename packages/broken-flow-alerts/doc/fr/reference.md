<!-- phil-dev source-sha256=84af27750e3b63304bce14db56fca547b6fa9f7a398d46c0ff7649ec6e04d55a -->
# Référence de l'API

Chaque type public de la bibliothèque, avec son rôle. Les espaces de noms sont relatifs à `Phil\BrokenFlowAlerts`.

| Type | Rôle |
|---|---|
| `Flow` | `of(string $id, ?string $label)`; `id`, `label` ; `MAX_LENGTH` = 128 |
| `BrokenFlowRule` | `evaluate(list<int>): FlowStatus`, `historyDays()` ; `baselineDays`, `windowDays`, `significance`, `weekly` ; `DEFAULT_*`, `MAX_BASELINE_DAYS`, `MAX_WINDOW_DAYS` ([detection.md](detection.md)) |
| `FlowStatus` | `broken`, `days`, `observed`, `expected`, `probability` |
| `BrokenFlowWatch` | `check(iterable<Flow>): list<FlowAlert>`, les alertes du dernier jour UTC entier, envoyées ([watch.md](watch.md)) |
| `FlowAlert` | `flow`, `kind`, `day`, `since`, `observed`, `expected` |
| `FlowAlertKind` | `Broken`, `Recovered` |
| `Contract\CompletionHistory` | `dailyCompletions(Flow, DateTimeImmutable, int): list<int>` ([extension-points.md](extension-points.md#completionhistory)) |
| `Contract\AlertSender` | `send(FlowAlert)` ([extension-points.md](extension-points.md#alertsender)) |
| `Notifier\NotifierAlertSender` | Un `AlertSender` sur Symfony Notifier ; `notificationOf(FlowAlert): Notification` |
| `Exception\BrokenFlowAlertsException` | Implémentée par chaque exception de la bibliothèque |
| `Exception\InvalidFlowValue` | Un parcours, un réglage ou un historique refusé ; `because()` |
| `Internal\PoissonTail` | `atMost(int, float)`, interne ; `EXACT_UP_TO` = 10 000 |
