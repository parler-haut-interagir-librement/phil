# Extension points

Two contracts, under `Phil\BrokenFlowAlerts\Contract`, leave to the application what only it knows: where the
completions are counted, and how an alert reaches someone.

| Contract | Method | Used for | Adapter in the library |
|---|---|---|---|
| `CompletionHistory` | `dailyCompletions(Flow, DateTimeImmutable, int): list<int>` | The completions of a flow, per UTC day | none |
| `AlertSender` | `send(FlowAlert): void` | Telling someone | `Notifier\NotifierAlertSender` |

## `CompletionHistory`

```php
interface CompletionHistory
{
    /** @return list<int> one count per UTC day, oldest first, exactly $days of them */
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array;
}
```

Invariants:

- **Exactly `$days` counts**, the first for the UTC day of `$firstDay`. A day without a completion is a `0`, never a
  missing entry: the watch refuses a history of another length.
- **Whole numbers from zero.** The rule refuses a negative count.
- **Read only.** The history reads counters the application keeps; it never writes them, and it reads nothing about a
  visitor.

An adapter over orders, in application code with Doctrine DBAL:

```php
final readonly class OrderCompletionHistory implements CompletionHistory
{
    public function __construct(private Connection $connection) {}

    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        $last = $firstDay->modify(sprintf('+%d days', $days));
        $perDay = $this->connection->fetchAllKeyValue(
            'SELECT DATE(paid_at), COUNT(*) FROM orders WHERE channel = ? AND paid_at >= ? AND paid_at < ? GROUP BY DATE(paid_at)',
            [$flow->id, $firstDay->format('Y-m-d'), $last->format('Y-m-d')],
        );

        $counts = [];

        for ($i = 0; $i < $days; ++$i) {
            $counts[] = (int) ($perDay[$firstDay->modify(sprintf('+%d days', $i))->format('Y-m-d')] ?? 0);
        }

        return $counts;
    }
}
```

Its `paid_at` is stored in UTC, so that a day of the query is a UTC day. `phil/analytics-bundle` provides an adapter over
its goal counters, `GoalCompletionHistory`.

## `AlertSender`

```php
interface AlertSender
{
    public function send(FlowAlert $alert): void;
}
```

`Notifier\NotifierAlertSender` is the library's ([watch.md](watch.md#notifications)). An adapter of the application can
translate the text, choose recipients per flow, or open an incident:

```php
final readonly class TranslatedAlertSender implements AlertSender
{
    public function __construct(private NotifierInterface $notifier, private TranslatorInterface $translator) {}

    public function send(FlowAlert $alert): void
    {
        $this->notifier->send(new Notification($this->translator->trans('flow.'.$alert->kind->value, [
            '%flow%' => $alert->flow->label,
            '%since%' => $alert->since,
        ])));
    }
}
```

An exception thrown by a sender stops the run: the flows after it are not checked. A sender that should not stop the
others catches its own failures.
