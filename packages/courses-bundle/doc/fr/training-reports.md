<!-- phil-dev source-sha256=55d4e86bf07c0f0f9fe879467a3f0c709e6717b6b024649e1e4beb802e4665ee -->
# Rapports de formation

Avec phil/reporting-bundle enregistré, les achèvements des [relevés](credits-and-transcripts.md) alimentent ses deux
rapports de formation : `training_hours`, les heures et les coûts de la formation par période et par unité
organisationnelle, et `training_quebec_1_percent`, le gabarit du 1 % de la masse salariale du Québec. Ce bundle est le
`TrainingSource` de ce bundle de rapports ; les rapports, leurs colonnes, leurs paramètres et leurs exports sont ceux de
phil/reporting-bundle, décrits dans sa documentation des heures de formation et du gabarit du 1 % de la masse salariale.

```sh
composer require phil/reporting-bundle
bin/console phil:reporting:export training_hours --period=last_year --format=csv
```

La source est enregistrée quand phil/reporting-bundle l'est, que la [formation obligatoire](mandatory-training.md) — qui
conserve les relevés — est activée et que `phil_courses.reporting.enabled` n'est pas faux. Une application qui fait de
`Phil\ReportingBundle\Application\Contract\TrainingSource` un alias vers un service à elle le conserve.

## Ce que devient un achèvement

Chaque ligne d'un relevé achevée dans la période est une activité de formation :

| Activité | D'après l'achèvement |
|---|---|
| Le moment où elle compte | Le moment où le cours a été terminé |
| Ses heures | Les crédits que l'achèvement a accordés : les heures telles quelles, les unités de formation continue à `ceu_hours` heures chacune, 10 par défaut ; aucune sans crédits. Une activité compte dix mille heures au plus |
| Son coût et son coût admissible | Ce que répond le `TrainingCosts` de l'application ; rien sans service |
| Son unité | L'unité de l'apprenant, d'après `TrainingUnits` ; aucune sans service |

Les achèvements sont lus dans les relevés ligne par ligne, les plus anciens d'abord, par un index sur le moment de
l'achèvement : un rapport sur une année garde un achèvement à la fois, en plus de ses totaux. Un achèvement entré de
nouveau par une recertification compte de nouveau ; un achèvement effacé avec les données personnelles de l'apprenant ne
compte plus, sauf si les relevés sont conservés (`phil_courses.training.keep_transcripts`).

## Les coûts : `TrainingCosts`

Ce qu'un achèvement a coûté à l'organisation, c'est à l'application de le dire : honoraires versés à un formateur,
salaires des heures passées, déplacements. Son service répond un `TrainingCost` pour chaque achèvement, en unités
mineures de la devise configurée, et la part de ce coût que compte la loi :

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

Un coût va de 0 à 10¹² unités mineures et sa part admissible de 0 au coût ; tout autre échoue avec `InvalidCourse`.
Quels coûts sont admissibles, c'est la loi que sert le rapport qui le fixe : le service décide, le rapport additionne.

## Les unités : `TrainingUnits`

L'unité dans laquelle compte la formation d'un apprenant. Avec phil/organizations-bundle enregistré et aucun service
nommé dans `phil_courses.reporting.units`, `OrganizationsTrainingUnits` y répond à partir de l'arbre d'unités : l'unité à
laquelle l'apprenant appartient aujourd'hui, le membre étant l'identifiant de l'apprenant. Un apprenant de plusieurs
unités compte dans la première par identifiant, dans la première organisation, par identifiant, où il appartient à une
unité. Le même bundle nomme les unités et place chacune sous son parent dans les rapports, par son propre adaptateur du
`UnitDirectory` du bundle de rapports.

Une application dont les apprenants changent d'unité, et dont les rapports suivent l'unité du jour de chaque achèvement,
nomme son propre service :

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

Une unité est nommée par 1 à 128 caractères ASCII imprimables. Les unités sont demandées une fois par apprenant et par
rapport, dix mille apprenants au plus étant gardés à la fois, et rien n'est conservé d'un rapport au suivant.

## La masse salariale

Le gabarit du 1 % de la masse salariale compare les coûts admissibles à la masse salariale de la période. Elle n'est
jamais lue dans les relevés ni dans un système de paie : l'application la fournit, comme paramètre `payroll` de la
demande ou par le `PayrollSource` de phil/reporting-bundle.

## Configuration

| Option | Défaut | Ce qu'elle fait |
|---|---|---|
| `reporting.enabled` | `true` | Si les achèvements sont la source de formation de phil/reporting-bundle quand il est enregistré. |
| `reporting.currency` | `CAD` | Le code ISO 4217 de la devise dans laquelle sont comptés les coûts, trois majuscules. |
| `reporting.ceu_hours` | `10` | Les heures d'une unité de formation continue, de 1 à 1000. |
| `reporting.costs` | `null` | Un service `TrainingCosts` ; chaque achèvement coûte zéro quand il est nul. |
| `reporting.units` | `null` | Un service `TrainingUnits` ; `OrganizationsTrainingUnits` quand il est nul et que phil/organizations-bundle est enregistré, aucun autrement. |
