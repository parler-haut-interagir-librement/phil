# Experiments (A/B tests)

An experiment splits the contexts a flag is on for between weighted variants, on one attribute of the context.

```yaml
flags:
    checkout-layout:
        active: true
        experiment:
            attribute: tenant
            variants: { one-column: 50, two-columns: 50 }
        kind: experiment
```

## Declaring

| Part | Type | Constraint |
|---|---|---|
| `attribute` | `TargetingAttribute` | `tenant`, `project` or `subject`. |
| `variants` | map of variant name to weight | At least two variants, each declared once. |
| Variant name | `VariantName` | The alphabet of flag names (lowercase ASCII segments separated by `.`, `_` or `-`), at most 50 bytes. A numeric key such as `1` is read as the name `"1"`. |
| Weight | `Variant` | A whole number from 0 to 100; the weights add up to exactly 100. |

`Experiment::of()` refuses anything else, and in the configuration the container build fails with the flag's
path.

## Who sees the experiment

The experiment applies **after** the active state and the rules ([flags-and-evaluation.md](flags-and-evaluation.md)):

| Situation | Result |
|---|---|
| Flag inactive | off, `DISABLED`, no variant |
| Active, rules present, none matches | off, `DEFAULT`, no variant |
| Active, rules absent or one matches, context has the attribute | **on, `SPLIT`, with its variant** |
| Active, rules absent or one matches, context lacks the attribute | off, `DEFAULT`, no variant |

So rules choose the population and the experiment splits it: `rules: [{ attribute: tenant, percentage: 10 }]`
with an experiment runs the test on a tenth of the tenants.

## Assignment

A value of the attribute falls into a bucket of its own (`Bucket::forVariant()`):

```text
bucket = murmur3a("<flag>:variant:<value>") mod 100
```

The buckets are handed to the variants **in their declared order, by cumulative weight**, each boundary
exclusive: with `control: 50, treatment: 50`, buckets 0 to 49 see `control` and 50 to 99 see `treatment`.

- **Stable.** The same tenant sees the same variant on every request and every server.
- **Salted apart from the rollout.** The `:variant:` salt keeps the tenants admitted first by a rollout of the flag
  from also being the ones in its first variant. A flag name holds no `:`, so the salt cannot collide with
  another flag's input.
- **A weight of 0** keeps a variant declared while sending it nobody. The weights still add up to 100, so its
  share goes to another arm; give it to a neighbouring arm and only the closed arm's contexts move, every other
  arm keeps its own.
- **Change the weights only between experiments.** Moving a boundary moves contexts from one arm to the other in
  the middle of a measurement.

### A one-way door

The variant bucket function is pinned by the tests (`ExperimentTest`), like the rollout bucket:

| Input | Bucket |
|---|---|
| `new-checkout:variant:tenant-1` | 48 |
| `new-checkout:variant:tenant-2` | 67 |
| `new-checkout:variant:42` | 98 |

Changing it moves the cohorts of every running experiment.

## Reading the variant

```php
$variant = $flags->variant(FlagName::of('checkout-layout'));   // ?VariantName
if ('two-columns' === $variant?->value) { /* … */ }
```

```twig
{% if feature_variant('checkout-layout') == 'two-columns' %}…{% endif %}
```

```php
#[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]   // 404 outside that arm
```

```yaml
condition: "service('feature_flags').variant('checkout-layout') == 'two-columns'"
```

See [placement.md](placement.md).

## Exposures

When an `exposure_recorder` is configured, `FlagEvaluator` calls `ExposureRecorder::record(FlagName $flag,
VariantName $variant)` on the **first** evaluation of a flag that yields a variant for a context in a request: the
moment the application asks is the moment it branches on the answer. Later evaluations of the same flag for the
same context in the same request are memoized and record nothing; a context that is not placed (off, no variant)
records nothing.

**An exposure is a count, never a record.** The recorder receives the flag and the variant and nothing of the
context: no tenant, no project, no subject. It adds one to an aggregate the application keeps ("variant
`treatment` of `checkout-layout` seen once more today"), and the application compares the arms on its own
aggregate measures (conversion counts, durations). A per-person exposure log would be a record of which person
saw what, which is exactly what the evaluation refuses to produce; an application that needs per-subject analysis
designs and justifies it on its own side.

Any evaluation counts as an exposure, wherever it comes from: the controller attribute, a route condition, a
Twig function or a service.

```php
use Phil\FeatureFlagsBundle\Application\Flag\ExposureRecorder;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;

final readonly class DailyExposureCounter implements ExposureRecorder
{
    public function __construct(private \Doctrine\DBAL\Connection $db, private \Psr\Clock\ClockInterface $clock) {}

    public function record(FlagName $flag, VariantName $variant): void
    {
        $this->db->executeStatement(
            'INSERT INTO flag_exposure (day, flag, variant, seen) VALUES (?, ?, ?, 1)
             ON CONFLICT (day, flag, variant) DO UPDATE SET seen = flag_exposure.seen + 1',
            [$this->clock->now()->format('Y-m-d'), $flag->value, $variant->value],
        );
    }
}
```

(The table and the upsert are the application's; the bundle ships no exposure store.)

## In the listing and the admin

The listing and the admin show an experiment's targeting as `A/B by <attribute>: <variant> <weight>%, …`
([console.md](console.md), [admin.md](admin.md)). In the admin's edit form, the experiment is written as JSON
next to the rules: `"experiment": {"attribute": "tenant", "variants": {"control": 50, "treatment": 50}}`.
