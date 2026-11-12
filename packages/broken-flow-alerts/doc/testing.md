# Testing

## How the library is tested

| Test | Covers |
|---|---|
| `tests/Unit/FlowTest.php` | Accepted and refused identifiers and labels |
| `tests/Unit/Internal/PoissonTailTest.php` | Exact tails, a mean of a thousand without underflow, the Chernoff bound past 10,000 |
| `tests/Unit/BrokenFlowRuleTest.php` | Busy, rare and silent flows, drops, the weekday rhythm, the quietest stretch, the significance at its boundary, refused settings and histories |
| `tests/Unit/BrokenFlowWatchTest.php` | The last whole UTC day, the alert on the day a flow breaks and not after, the recovery, a silence that became the rhythm, the order of the flows |
| `tests/Unit/Notifier/NotifierAlertSenderTest.php` | The subject, content and importance of each alert, with and without recipients |

Each test class declares `#[CoversClass]` for the types it covers. The tests use no container, no database and no
network; the clock is a `MockClock`.

## Running the tests

From the root of the PHIL monorepo:

```bash
vendor/bin/phpunit packages/broken-flow-alerts/tests
```

Installed on its own, as a project that requires it sees it:

```bash
composer install
vendor/bin/phpunit --no-configuration tests
```

## Testing an adapter

A `CompletionHistory` is tested against the counters it reads: a day without a completion is a `0`, the first count is
the day of `$firstDay`, and the length is exactly `$days`. A watch over a scripted history and a recording sender tests
what the application does with the alerts:

```php
$history = new class implements CompletionHistory {
    public function dailyCompletions(Flow $flow, DateTimeImmutable $firstDay, int $days): array
    {
        return [...array_fill(0, $days - 1, 20), 0];             // twenty a day, then none
    }
};

$alerts = new BrokenFlowWatch($history, $sender, new MockClock('2026-11-12 06:00 UTC'))->check([Flow::of('checkout')]);
// one Broken alert for 2026-11-11
```
