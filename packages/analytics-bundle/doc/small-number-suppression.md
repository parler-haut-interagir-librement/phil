# Small-number suppression

On a small site, a counter of one or two designates the person it counts. **A counter under the threshold is
never shown on its own.** The rule is applied **on read, never on write**: the stored counter stays whole, and
only what a dashboard shows is grouped.

State: `SmallNumberThreshold`, `SmallNumberSuppression` and `SuppressedCounts` are implemented and tested; the bundle
wires `SmallNumberSuppression` with `phil_analytics.small_number_threshold`. Dashboards, and who may read them, are the
application's.

## `SmallNumberThreshold`

```php
$threshold = SmallNumberThreshold::of(5);
$threshold->value;        // 5
$threshold->isMetBy(4);   // false
$threshold->isMetBy(5);   // true
```

The value is the application's to choose. The bundle only refuses a threshold under 2, which would suppress
nothing. Choose it above the count at which a value designates a person on your smallest site.

## `SmallNumberSuppression`

```php
$counts = $store->counts($site, 'pageviews', 'country', UtcDay::fromString('2026-10-01'), UtcDay::fromString('2026-10-31'));
$result = new SmallNumberSuppression(SmallNumberThreshold::of(5))->apply($counts);

$result->shown;   // array<string, int>: counters shown on their own, largest first
$result->others;  // ?int: the "other" group, or null when it must not be shown
$result->hidden;  // int: how many counters are shown neither on their own nor in a group
```

`apply(array<string, int> $counts): SuppressedCounts`:

1. A negative counter throws `InvalidValue`. A counter of zero is no counter and is left out.
2. A counter at or above the threshold is shown on its own — **except the counter stored under
   `CounterKey::OTHER`**, which is never shown under its own name.
3. A counter under the threshold, and the counter stored under `(other)`, join the "other" group.
4. **The group is shown only if its sum meets the threshold.** A group of a single small counter would show the
   very value it is meant to hide, and a group of several whose sum is under the threshold gives them away too:
   every counter is at least 1, so two counters summing to 2 are each 1. Such counters are hidden.

## Examples (threshold 5)

| Input | `shown` | `others` | `hidden` |
|---|---|---|---|
| `['CA' => 5, 'FR' => 12]` | `['FR' => 12, 'CA' => 5]` | `null` | 0 |
| `['CA' => 40, 'BE' => 2, 'CH' => 3]` | `['CA' => 40]` | 5 | 0 |
| `['CA' => 40, 'BE' => 1, 'CH' => 2]` | `['CA' => 40]` | `null` | 2 |
| `['CA' => 40, 'BE' => 2]` | `['CA' => 40]` | `null` | 1 |
| `['home' => 40, '(other)' => 3, 'event' => 2]` | `['home' => 40]` | 5 | 0 |
| `['(other)' => 7]` | `[]` | 7 | 0 |
| `['(other)' => 4]` | `[]` | `null` | 1 |
| `['CA' => 40, 'BE' => 0, 'CH' => 1]` | `['CA' => 40]` | `null` | 1 |
| `[]` | `[]` | `null` | 0 |

Note the second and third rows: two small counters are shown as a group when their sum reaches the threshold, and
hidden when it does not. A shown group reveals a total of at least the threshold, never one of its counters.

## Why on read

- The stored data stays exact, so changing the threshold later needs no migration and loses nothing.
- Different readers may be given different thresholds by the application.
- Ingestion stays one atomic increment per counter, with no read-modify-write.

The counterpart: **every read path must go through `SmallNumberSuppression`**. A dashboard, an export or an API
that reads `CounterStore::counts()` directly shows counters that designate people.
