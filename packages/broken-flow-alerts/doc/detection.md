# Detection

`BrokenFlowRule` decides whether a flow is broken from its daily completions. It reads a history of
`historyDays()` counts, oldest first, in two parts:

| Part | Days | Role |
|---|---|---|
| The baseline | `baselineDays`, 28 by default (7 to 366) | Sets the rhythm: how many completions each day is expected to bring |
| The window | `windowDays`, 7 by default (1 to 31) | The last days, compared with the rhythm |

```php
use Phil\BrokenFlowAlerts\BrokenFlowRule;

$rule = new BrokenFlowRule();                                   // 28 + 7 days, 0.001, weekly
$status = $rule->evaluate($history);                            // exactly 35 counts

$status->broken;       // bool
$status->days;         // how many of the last days the numbers cover
$status->observed;     // the completions over those days
$status->expected;     // the completions the rhythm expects over those days
$status->probability;  // how likely so few completions are in a working flow
```

## The rhythm of a day

With `weekly` (the default) and a baseline of two weeks or more, each day of the window is expected to bring the mean of
the baseline days of the same weekday: a shop closed on Sundays has a usual Sunday of zero, and a silent Sunday is not a
drop. Otherwise, every day is expected to bring the mean of the whole baseline. The weekday is found by position — a whole
number of weeks before the day —, so the history needs no dates.

A baseline that is a whole number of weeks gives each weekday the same weight; 28 days give four of each.

## The Poisson count

Completions arrive one by one, independently enough that a day's count follows a Poisson law around its expected value.
For each stretch of the window that ends on its last day — the last day, the last two days, and so on up to the whole
window —, the rule sums the completions observed and expected, and computes `P(X <= observed)` for `X` a Poisson count of
that mean. The stretch with the lowest probability is the one reported, and **the flow is broken when that probability is
under `significance`**.

| A flow's usual day | Silent days before it is broken, at 0.001 |
|---|---|
| 20 completions | 1 (`e^-20`) |
| 5 completions | 2 (`e^-10`) |
| 2 completions | 4 (`e^-8`) |
| 1 completion | 7 (`e^-7`) |
| 0.9 completion or fewer | never, within a window of 7 days |

A drop is broken too, not only a silence: a tenth of the usual completions over three days of a flow that usually brings
50 a day is far under 0.001. A count at or above what the rhythm expects is never a drop.

The stretches all end on the last day, so a normal last day does not hide the silent days before it: two silent days and
a normal one still weigh as three days far under their rhythm, until the silent days leave the window.

## Bounded work

The tail is summed term by term in logarithms, so that a mean of thousands does not underflow to zero, up to
`PoissonTail::EXACT_UP_TO` (10,000) completions; above, the Chernoff bound `exp(-mean) (e mean / k)^k` stands in for the
sum. The bound is never below the exact tail, so it can only make an alert less likely. A check costs at most
`windowDays` sums of 10,001 terms per flow, whatever the counts.

## The settings

| Setting | Lower | Higher |
|---|---|---|
| `baselineDays` | Follows a change of rhythm sooner; a short spell of silence weighs more on the rhythm | Steadier rhythm; a lasting change of rhythm takes longer to become the norm |
| `windowDays` | Only short silences are weighed; a rare flow may never be broken | Rare flows can be broken after a longer silence; a past silence keeps a flow broken longer |
| `significance` | Fewer false alerts, and a true one later | Earlier alerts, and more false ones |
| `weekly: false` | — | Every day has the same rhythm: for a flow without a weekly pattern, or a baseline under two weeks |

Refused settings throw `InvalidFlowValue`: a baseline outside 7 to 366 days, a window outside 1 to 31 days, a significance
that is not above 0 and under 0.5. A history of the wrong length, or with a negative count, is refused the same way.
