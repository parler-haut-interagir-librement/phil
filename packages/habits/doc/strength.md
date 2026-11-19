# Strength

The strength is the number shown beside the streaks: from 0 to 100 %, it says how well a habit has been kept lately.
It rises with each period met and falls a little with each period missed, so that one bad week after a good season
costs a few points rather than everything.

## The formula

The strength `s` starts at 0. After each period met or missed, it moves towards how much of the period was done, `c`,
from 0 to 1, by a weight `w` that depends on the number of days `d` the period stands for:

```text
w = 1 − 2^(−d / h)
s′ = s + w × (c − s)
```

`h` is the half-life, fourteen days by default: after fourteen days, a check-in weighs half of what it weighed. It is an
exponentially smoothed average whose memory is counted in days rather than in periods:

- a daily habit done every day for fourteen days reaches 50 %, for twenty-eight days 75 %, for two months 95 %;
- a week done three times out of three weighs as much as seven days done one by one, so that the same effort reaches
  the same strength whatever the schedule;
- a missed day takes away about 5 % of the strength there is, a missed week about 29 %;
- a day done in part moves the strength towards its share: half of the target pulls it towards 50 %.

A period met counts for 1. A period missed counts for its share, done units over the units it asks. An excused period,
and the running period not yet met, leave the strength as it is.

`HalfLifeStrength` implements this formula; `new HalfLifeStrength(7.0)` gives a strength that reacts faster, `30.0`
one that remembers longer. The half-life runs from 1 to 365 days.

## Reading it

`Progress::$strength` is the number from 0 to 1; `strengthPercent()` rounds it to a whole percentage, the way it is
shown. Show it beside the streaks rather than instead of them: the streak tells a run, the strength tells a trend that
a single missed day does not wipe out.

## Another formula

The strength model is a port, `Contract\StrengthModel`: `next($strength, $completion, $days)` returns the strength after
a period. Pass another model to `ProgressCalculator`, and that calculator to `Habits`.

## The cost

The progress is worked out from the habit's first day at each read: one pass over the periods, with the check-ins in
memory. A benchmark holds the cost of ten years of a daily habit — 3,653 periods — to a few milliseconds.
