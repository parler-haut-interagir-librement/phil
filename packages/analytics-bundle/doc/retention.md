# Retention

Daily counters are kept for 13 whole months, then only their monthly totals remain. A month can then be compared with
the same month a year earlier, while the detail of each day does not linger.

State: `CounterRetention`, `UtcMonth` and the default rollup, `DbalCounterRollup`, are implemented and tested. The bundle
runs the rollup once a day on the schedule `phil_analytics` ([scheduled-jobs.md](scheduled-jobs.md#retention)); set
`retention.months` to keep the days longer or shorter.

```php
$retention = new CounterRetention();                        // 13 months; new CounterRetention(6) for 6
$bound = $retention->firstDailyDay(UtcDay::of($now));       // 2025-10-01 on 2026-11-09

$rollup->rollUpBefore($bound, $threshold);
```

The bound is always the first day of a month, so only whole months are rolled up.

`rollUpBefore()` adds the days before the bound to their monthly totals and deletes them, in one transaction. The
monthly total of an open dimension (`MeasureNames::OPEN_DIMENSIONS`: hosts and search terms) under the threshold is not
kept: a rare term is forgotten rather than stored for good.

To read further back than the daily counters, `monthlyCounts()` sums the totals of a range of months. The small-number
rule applies as for any read.
