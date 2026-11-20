# Training hours and costs

The report `training_hours` totals the training of an organisation by period and by organisational unit: the number
of activities, their hours, their costs and the part of those costs the law counts. The figures come from the
learning system through a port; the tree of the units, from the application's directory of units through another.
The bundle depends on neither: an application connects them with two small adapters.

| Port | Implemented by | Required |
|---|---|---|
| `TrainingSource` | The learning system: courses completed, sessions attended | Yes: without one, the training reports are not registered |
| `UnitDirectory` | The directory of the organisational units, a tree | No: without one, each unit is reported alone, named by its identifier |
| `PayrollSource` | The payroll system | No: read by the [1 % of payroll template](quebec-one-percent.md) alone |

## The activities: `TrainingSource`

```php
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class CompletedCourses implements TrainingSource
{
    public function __construct(private CompletionRepository $completions) {}

    public function currency(): string
    {
        return 'CAD';
    }

    public function activities(Period $period): iterable
    {
        foreach ($this->completions->iterateBetween($period->start, $period->end) as $completion) {
            yield new TrainingActivity(
                on: $completion->completedAt,
                hundredthsOfHours: $completion->hours->hundredths,
                cost: $completion->costInCents,
                eligibleCost: $completion->eligibleCostInCents,
                unit: $completion->learnerUnitId,
            );
        }
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\ReportingBundle\Application\Contract\TrainingSource: '@App\Reporting\CompletedCourses'
```

A `TrainingActivity` is:

| Field | Meaning | Bounds |
|---|---|---|
| `on` | The moment the activity counts on; it falls in the period asked for, or it is left out | |
| `hundredthsOfHours` | Its hours in hundredths, as a learning system that grants credits in hours keeps them: `150` for an hour and a half | 0 to 1 000 000 (ten thousand hours) |
| `cost` | What it cost, in minor units of the source's currency: cents for dollars | 0 to 10¹² |
| `eligibleCost` | The part of the cost the law counts | 0 to the cost |
| `unit` | The identifier of the learner's organisational unit, or `null` | 1 to 128 printable ASCII characters |

An activity out of these bounds fails with `InvalidFigures` where the source builds it. Every cost is in the currency
`currency()` names, an ISO 4217 code; the report writes the amounts as decimal numbers of that currency, with its
digits — two for `CAD`, none for `JPY`.

Yield the activities from a query iterated row by row: the report reads them once and holds their totals, never the
activities. Learning systems differ in what they call an activity — a course completed, a session attended, an
external training declared by a manager —: the source decides what counts, and may yield several kinds.

## The tree of the units: `UnitDirectory`

```php
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

interface UnitDirectory
{
    /** @return list<UnitNode> the units from the root to the unit itself, the unit last; empty for an unknown unit */
    public function path(string $unit): array;
}
```

With phil/organizations-bundle 0.2, whose `UnitTree` answers the questions of other modules about its tree of units,
the adapter is:

```php
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

final readonly class OrganizationUnits implements UnitDirectory
{
    public function __construct(private UnitTree $tree) {}

    public function path(string $unit): array
    {
        $found = $this->tree->unit(UnitId::fromString($unit));

        if (!$found instanceof Unit) {
            return [];
        }

        return array_map(
            static fn (Unit $node): UnitNode => new UnitNode($node->id->value, $node->name->value),
            [...$this->tree->ancestors($found->id), $found],
        );
    }
}
```

```yaml
services:
    Phil\ReportingBundle\Application\Contract\UnitDirectory: '@App\Reporting\OrganizationUnits'
```

The report asks for the path of each unit once per export. A unit the directory does not know — a unit removed since
— is reported alone, named by its identifier.

## The report

| Column | Value |
|---|---|
| Start, End | The first and the last day of the period |
| Unit, Unit name | The unit's identifier and name; empty and « No unit » for the activities without a unit |
| Level | The depth of the unit in its tree, 1 for a root; 0 for the activities without a unit |
| Activities | The number of activities |
| Hours | The hours, with two decimals at most |
| Costs, Eligible costs | The amounts, in the currency's digits |
| Currency | The ISO 4217 code of the source |

The rows follow the periods, then the tree: a unit after its parent, the units of a parent ordered by name, then by
identifier; the activities without a unit last. A unit without activities in a period has no row.

| Parameter | Value | Default |
|---|---|---|
| `step` | `month`, `quarter` or `year`: one row per unit for each month, three months or year from the start of the period | The whole period |
| `include_below` | `true`: a unit's row counts the activities of the units below it, each of which has its own row too; `false`: each unit alone | `true` |
| `unit` | The identifier of a unit: its activities and those of the units below it alone, the units above it left out | Every unit |
| `locale` | The language of the headings, `fr` or `fr_CA`, through the translator | The translator's locale |

With `include_below`, a column summed over every row counts an activity once per level of its unit: sum the rows of
level 1, or ask with `include_below: false`.

```sh
bin/console phil:reporting:export training_hours --period=last_year --format=csv
```

```php
$queue->queue(new ReportJob('training_hours', 'csv', new ReportRequest($periods->period(PeriodPreset::LastYear), [
    'step' => 'quarter',
    'unit' => $unitOfTheManager,
]), 'mail', ['manager@example.com']));
```

The report is exported in any format of the bundle — CSV, TSV, JSON —, streamed as a download, scheduled and
delivered like any report: see [Reports](reports.md). Which unit a reader may ask for is the application's to check,
in the controller that streams the report or before it queues the job.

## Limits

A report holds at most 50 000 totals — a period and a unit for each — and stops with `ReportTooLarge` beyond: twelve
months of four thousand units. The step cuts the period in 500 parts at most, as `Period::split()` does.
