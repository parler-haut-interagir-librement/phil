# Meters and periods

## Meters

A `Meter` names what is counted, how time is cut for it, and the limit of a subject that no limit provider names —
usually the free tier's:

```php
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;

$meters = new Meters(
    new Meter('projects', CalendarPeriod::year(), Limit::of(3)),
    new Meter('ai.tokens', CalendarPeriod::month(), Limit::of(50_000)),
);
```

A name is up to 64 lowercase letters, digits, `_`, `.` or `-`, starting with a letter or a digit; it is what the
store keeps and what a subscription feature names. `Meters` refuses a name declared twice; `get()` throws
`UnknownMeter` for a name it does not hold, `has()` asks, `names()` lists them in order.

## Windows

At any moment a meter's period gives a `Window`: the first instant counted (`start`), when the window next changes
(`end`), and the counter that receives what is counted now (`counterStart`, `counterEnd`). For a calendar or billing
period, the window is the period and its single counter. For a rolling window, the counters are buckets.

## Calendar periods

`CalendarPeriod` counts by `CalendarUnit` — day, ISO week (from Monday), month or year — from local midnight in a
time zone, UTC by default. Every subject starts again together, on the first of the month in that zone:

```php
CalendarPeriod::day('Europe/Paris');
CalendarPeriod::week();
CalendarPeriod::month('America/Toronto');
CalendarPeriod::year();
```

A day on which the offset changes lasts 23 or 25 hours; the period follows the wall clock. An unknown zone throws
`InvalidUsage`.

## Billing cycles

`BillingCycle` counts periods of 1 to 120 months from an anchor, in the anchor's time zone: a subscription taken on
14 March at 10:30 starts a new period on the 14th of each month at 10:30.

```php
use Phil\UsageMeter\Period\BillingCycle;

BillingCycle::monthly(new DateTimeImmutable('2026-03-14 10:30', new DateTimeZone('America/Toronto')));
BillingCycle::yearly($anchor);
new BillingCycle($anchor, 3); // quarterly
```

An anchor on the 29th, 30th or 31st falls on the last day of a shorter month and comes back to its own day after:
31 January, 28 February, 31 March, 30 April. A moment before the anchor belongs to the periods before it, counted the
same way.

**Each subject's own anchor.** Subscribers rarely share a billing day. An `AnchorProvider` gives a subject's anchor —
the day it subscribed, read from the application —, and it wins over the one the cycle was built with, which serves
the subjects that have none:

```php
use Phil\UsageMeter\AnchorProvider;
use Phil\UsageMeter\Meter;

final readonly class SubscriptionAnchors implements AnchorProvider
{
    public function anchorFor(string $subject, Meter $meter): ?DateTimeImmutable
    {
        return $this->subscriptions->startOf($subject); // null: the cycle's own anchor
    }
}

new UsageMeter($meters, $store, $clock, anchors: new SubscriptionAnchors(...));
```

Calendar periods and rolling windows ignore anchors.

## Rolling windows

`RollingWindow` counts the last so many seconds, cut into 1 to 1440 buckets that divide the window exactly. A window
of 24 hours in 24 buckets counts the current hour and the 23 before it, and frees an hour's use as that hour leaves the
window:

```php
use Phil\UsageMeter\Period\RollingWindow;

RollingWindow::minutes(10); // 10 buckets of one minute
RollingWindow::hours(24);   // 24 buckets of one hour
RollingWindow::days(30);    // 30 buckets of one day
new RollingWindow(3600);    // one bucket: fixed blocks of an hour
```

Buckets are aligned on the Unix epoch, so every subject shares them and they never depend on a time zone. The more
buckets, the closer the window slides to the second, and the more counters a subject holds at once. `Usage::$resetsAt`
of a rolling window is when the oldest bucket leaves it — when some of the use is freed, not all of it.
