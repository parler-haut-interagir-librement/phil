# Training reports

With phil/reporting-bundle registered, the completions of the [transcripts](credits-and-transcripts.md) feed its two
training reports: `training_hours`, the hours and costs of the training by period and by organisational unit, and
`training_quebec_1_percent`, the template of Quebec's 1 % of payroll. The bundle is that report bundle's
`TrainingSource`; the reports, their columns, their parameters and their exports are those of
phil/reporting-bundle, described in its documentation of the training hours and of the 1 % of payroll template.

```sh
composer require phil/reporting-bundle
bin/console phil:reporting:export training_hours --period=last_year --format=csv
```

The source is registered when phil/reporting-bundle is registered, the [mandatory training](mandatory-training.md) —
which keeps the transcripts — is enabled and `phil_courses.reporting.enabled` is not false. An application that
aliases `Phil\ReportingBundle\Application\Contract\TrainingSource` to a service of its own keeps it.

## What a completion becomes

Each line of a transcript completed in the period is one training activity:

| Activity | From the completion |
|---|---|
| The moment it counts on | The moment the course was completed |
| Its hours | The credits the completion granted: hours as they are, continuing education units at `ceu_hours` hours each, 10 by default; none without credits. An activity counts ten thousand hours at most |
| Its cost and eligible cost | What the application's `TrainingCosts` answers; nothing without one |
| Its unit | The learner's unit, from `TrainingUnits`; none without one |

The completions are read from the transcripts row by row, the oldest first, through an index on the moment of
completion: a report over a year holds one completion at a time, besides its totals. A completion entered again by a
recertification counts again; one erased with the learner's personal data no longer counts, unless the transcripts are
kept (`phil_courses.training.keep_transcripts`).

## The costs: `TrainingCosts`

What a completion cost the organisation is the application's to say: fees paid to a trainer, the wages of the hours
spent, travel. Its service answers a `TrainingCost` for each completion, in minor units of the configured currency,
and the part of that cost the law counts:

```php
use Phil\CoursesBundle\Application\Contract\TrainingCosts;
use Phil\CoursesBundle\Application\Training\TrainingCost;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;

final readonly class CourseFees implements TrainingCosts
{
    public function __construct(private FeeRepository $fees) {}

    public function cost(TranscriptEntry $entry): TrainingCost
    {
        $fee = $this->fees->ofCourse($entry->course->value);

        return null === $fee ? TrainingCost::none() : new TrainingCost($fee->cents, $fee->eligibleCents);
    }
}
```

```yaml
phil_courses:
    reporting:
        currency: CAD
        costs: App\Training\CourseFees
```

A cost is from 0 to 10¹² minor units and its eligible part from 0 to the cost; another fails with `InvalidCourse`.
Which costs are eligible is set by the law the report serves: the service decides, the report adds up.

## The units: `TrainingUnits`

The unit the training of a learner counts in. With phil/organizations-bundle registered and no service named in
`phil_courses.reporting.units`, `OrganizationsTrainingUnits` answers it from the tree of units: the unit the learner
belongs to today, the member being the learner's identifier. A learner of several units counts in the first by
identifier, in the first organization, by identifier, where they belong to any. The same bundle names the units and
places each under its parent in the reports, through its own adapter of the report bundle's `UnitDirectory`.

An application whose learners change units, and whose reports follow the unit of the day of each completion, names
its own service:

```php
use Phil\CoursesBundle\Application\Contract\TrainingUnits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

final readonly class EmployeeDepartments implements TrainingUnits
{
    public function __construct(private EmployeeRepository $employees) {}

    public function unitOf(LearnerId $learner): ?string
    {
        return $this->employees->find($learner->value)?->departmentId;
    }
}
```

```yaml
phil_courses:
    reporting:
        units: App\Training\EmployeeDepartments
```

A unit is named by 1 to 128 printable ASCII characters. The units are asked once per learner and per report, at most
ten thousand learners held at a time, and nothing is kept from one report to the next.

## The payroll

The 1 % of payroll template compares the eligible costs with the payroll of the period. The payroll is never read
from the transcripts nor from a payroll system: the application gives it, as the `payroll` parameter of the request or
through the `PayrollSource` of phil/reporting-bundle.

## Configuration

| Option | Default | What it does |
|---|---|---|
| `reporting.enabled` | `true` | Whether the completions are the training source of phil/reporting-bundle when it is registered. |
| `reporting.currency` | `CAD` | The ISO 4217 code of the currency the costs are counted in, three capital letters. |
| `reporting.ceu_hours` | `10` | The hours of a continuing education unit, from 1 to 1000. |
| `reporting.costs` | `null` | A `TrainingCosts` service; every completion costs nothing when null. |
| `reporting.units` | `null` | A `TrainingUnits` service; `OrganizationsTrainingUnits` when null and phil/organizations-bundle is registered, none otherwise. |
