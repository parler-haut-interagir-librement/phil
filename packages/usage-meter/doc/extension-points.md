# Extension points

The library is plain PHP and declares no service. `UsageMeter` takes four ports and a clock; each has a shipped
implementation and may be the application's.

## `UsageStore`

`Phil\UsageMeter\UsageStore` — where the counters live. `InMemoryUsageStore` keeps one process's,
`Dbal\DbalUsageStore` two tables of a Doctrine DBAL connection. See [Storage](storage.md).

| Method | Contract |
|---|---|
| `add(Counter $counter, int $amount, ?int $ceiling = null, ?string $key = null): Increment` | Adds atomically: under a ceiling, two increments at the same moment are both counted or one is refused, never lost; a key recorded once is never counted twice (`Increment::Duplicate`); a refused increment leaves neither its amount nor its key behind |
| `total(string $subject, string $meter, DateTimeImmutable $from, DateTimeImmutable $before): int` | The sum of the counters that start in the window |
| `purge(DateTimeImmutable $now): int` | Forgets the counters and keys that no window still reads; returns how many |

A store of the application's own — Redis, say — keeps the three promises of `add()`, or quotas stop being quotas, and
the promise of `refund()`: never more back than a key counted, however often it is asked.

## `LimitProvider`

`Phil\UsageMeter\LimitProvider` — a subject's limit of a meter, read at each call. See [Limits](limits.md).

| Method | Contract |
|---|---|
| `limitFor(string $subject, Meter $meter): ?Limit` | The subject's limit, or `null` to let the next provider, then the meter's own limit, answer |

`LimitChain` asks several in turn; `Subscription\FeatureLimits` reads limits written `usage:<meter>:<limit>` in the
features of a subscription.

## `AnchorProvider`

`Phil\UsageMeter\AnchorProvider` — a subject's own anchor for billing cycles. See
[Meters and periods](meters-and-periods.md).

| Method | Contract |
|---|---|
| `anchorFor(string $subject, Meter $meter): ?DateTimeImmutable` | The day the subject's cycle counts from, or `null` for the cycle's own anchor |

## `PeriodPolicy`

`Phil\UsageMeter\Period\PeriodPolicy` — how a meter cuts time. `CalendarPeriod`, `BillingCycle` and `RollingWindow`
are shipped.

| Method | Contract |
|---|---|
| `windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window` | The window that holds the moment, and the counter it is counted in; a policy without anchors ignores `$anchor` |

## The clock

Any PSR-20 `ClockInterface`: the application's clock in production, a frozen one in tests.
