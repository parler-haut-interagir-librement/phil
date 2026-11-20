# The 1 % of payroll template (Quebec)

Quebec's *Act to promote workforce skills development and recognition* (CQLR, chapter D-8.3) asks an employer whose
total payroll for a calendar year exceeds two million dollars to invest at least 1 % of that payroll in eligible
training expenditures, or to pay the difference to the Workforce Skills Development and Recognition Fund. The report
`training_quebec_1_percent` gathers the figures of that obligation from the [training source](training.md) and the
payroll.

The report computes; it does not decide. Which expenditures are eligible, what the total payroll includes and how the
year is declared are set by the Act, its regulations and Revenu Québec: the training source yields the eligible cost
of each activity as the employer counts it, and the payroll source or the request gives the total payroll as the Act
defines it.

## The columns

| Column | Value |
|---|---|
| Start, End | The first and the last day of the period: a calendar year for the Act |
| Activities, Hours | The training activities of the period and their hours |
| Costs | Their costs |
| Eligible costs | The part of their costs the Act counts |
| Payroll | The total payroll of the period |
| Required investment (1%) | 1 % of the payroll, rounded half up to the cent |
| Eligible costs over payroll (%) | The eligible costs as a percentage of the payroll, two decimals |
| Shortfall | What the eligible costs lack to reach the required investment; 0 when they reach it |
| Payroll above the threshold | Whether the payroll exceeds two million dollars, which makes the Act apply |
| Currency | The ISO 4217 code of the training source |

Without a payroll, the five columns that depend on it are empty. A payroll of zero requires nothing and has no
percentage.

Every unit of the organisation counts together: the obligation is the employer's. The report of each unit is
[`training_hours`](training.md#the-report).

## The payroll

The payroll comes, in order, from:

1. the request's `payroll` parameter, in minor units — cents —, for a report of one period;
2. the application's `PayrollSource`, asked for each period;
3. nothing: the payroll columns are empty.

```php
use Phil\ReportingBundle\Application\Contract\PayrollSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class DeclaredPayroll implements PayrollSource
{
    public function __construct(private PayrollRepository $payrolls) {}

    public function payroll(Period $period): ?int
    {
        return $this->payrolls->totalInCentsBetween($period->start, $period->end);
    }
}
```

```yaml
services:
    Phil\ReportingBundle\Application\Contract\PayrollSource: '@App\Reporting\DeclaredPayroll'
```

A payroll is in the training source's currency, from 0 to 10¹⁵ minor units; another fails with `InvalidFigures`.

## Asking for it

| Parameter | Value | Default |
|---|---|---|
| `step` | `month`, `quarter` or `year`, from the start of the period | The whole period |
| `payroll` | The total payroll in minor units, a whole number of zero or more; refused with a `step` that gives more than one period | The payroll source |
| `locale` | The language of the headings: `fr` writes them in French | The translator's locale |

```sh
bin/console phil:reporting:export training_quebec_1_percent --period=last_year --format=csv
```

```php
$quebec = new DateTimeZone('America/Toronto');
$request = new ReportRequest(
    new Period(new DateTimeImmutable('2026-01-01', $quebec), new DateTimeImmutable('2027-01-01', $quebec)),
    ['payroll' => 412_345_600, 'locale' => 'fr'],
);

return $responses->download('training_quebec_1_percent', 'csv', $request);
```

```csv
Début,Fin,Activités,Heures,Coûts,"Dépenses admissibles","Masse salariale","Investissement requis (1 %)","Dépenses admissibles sur masse salariale (%)","Écart à combler","Masse salariale au-dessus du seuil",Devise
2026-01-01,2026-12-31,1840,9612.5,61250.4,48210.75,4123456,41234.56,1.17,0,true,CAD
```

The `last_year` preset is a calendar year in the configured time zone: set `phil_reporting.timezone` to
`America/Toronto`, Quebec's, for the year the Act counts. The report is exported in any format of the bundle,
scheduled every year and delivered like any report: see [Reports](reports.md).

## The threshold

The threshold is the service's fourth argument, in minor units: `200000000`, two million dollars. Change it with the
service's definition when the Act changes it:

```yaml
services:
    Phil\ReportingBundle\Application\Training\QuebecTrainingReport:
        arguments:
            $threshold: 250000000
```

Read the threshold against a payroll in dollars: in another currency, the column says nothing of the Act.
