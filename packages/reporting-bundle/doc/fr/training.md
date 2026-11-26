<!-- phil-dev source-sha256=27ee1cf31771eb724b09a8cde1508e031af43e21a2a09bfa664829827bd8c152 -->
# Heures et coûts de formation

Le rapport `training_hours` totalise la formation d'une organisation par période et par unité organisationnelle : le
nombre d'activités, leurs heures, leurs coûts et la part de ces coûts que la loi compte. Les chiffres viennent du
système d'apprentissage par un port ; l'arbre des unités, du répertoire des unités de l'application par un autre. Le
bundle ne dépend ni de l'un ni de l'autre : phil/courses-bundle et phil/organizations-bundle se branchent d'eux-mêmes
quand ils sont installés, et une application branche tout autre système avec deux petits adaptateurs.

| Port | Implémenté par | Requis |
|---|---|---|
| `TrainingSource` | Le système d'apprentissage : cours terminés, séances suivies ; phil/courses-bundle | Oui : sans lui, les rapports de formation ne sont pas enregistrés |
| `UnitDirectory` | Le répertoire des unités organisationnelles, un arbre ; phil/organizations-bundle | Non : sans lui, chaque unité est rapportée seule, nommée par son identifiant |
| `PayrollSource` | Le système de paie | Non : lu seulement par le [gabarit du 1 % de la masse salariale](quebec-one-percent.md) |

## Les activités : `TrainingSource`

Avec phil/courses-bundle enregistré et sa formation obligatoire activée, les achèvements de ses relevés sont la source :
chaque achèvement est une activité, ses heures sont celles des crédits qu'il a accordés, son coût et l'unité de
l'apprenant ce que répondent les ports de l'application de ce bundle. Sa documentation des rapports de formation les
décrit. Un autre système d'apprentissage implémente le port :

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

Une `TrainingActivity` est :

| Champ | Sens | Bornes |
|---|---|---|
| `on` | Le moment où l'activité compte ; il tombe dans la période demandée, sinon l'activité est écartée | |
| `hundredthsOfHours` | Ses heures en centièmes, comme les garde un système d'apprentissage qui accorde des crédits en heures : `150` pour une heure et demie | 0 à 1 000 000 (dix mille heures) |
| `cost` | Ce qu'elle a coûté, en unités mineures de la devise de la source : des cents pour des dollars | 0 à 10¹² |
| `eligibleCost` | La part du coût que la loi compte | 0 jusqu'au coût |
| `unit` | L'identifiant de l'unité organisationnelle de l'apprenant, ou `null` | 1 à 128 caractères ASCII imprimables |

Une activité hors de ces bornes échoue avec `InvalidFigures` là où la source la construit. Chaque coût est dans la
devise que nomme `currency()`, un code ISO 4217 ; le rapport écrit les montants comme des nombres décimaux de cette
devise, avec ses décimales — deux pour `CAD`, aucune pour `JPY`.

Produisez les activités à partir d'une requête parcourue ligne par ligne : le rapport les lit une fois et garde leurs
totaux, jamais les activités. Les systèmes d'apprentissage diffèrent sur ce qu'ils appellent une activité — un cours
terminé, une séance suivie, une formation externe déclarée par un gestionnaire — : la source décide de ce qui compte, et
peut produire plusieurs sortes d'activités.

## L'arbre des unités : `UnitDirectory`

```php
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

interface UnitDirectory
{
    /** @return list<UnitNode> the units from the root to the unit itself, the unit last; empty for an unknown unit */
    public function path(string $unit): array;
}
```

Avec phil/organizations-bundle installé, son `ReportingUnitDirectory` est le répertoire : le chemin d'une unité depuis sa
racine, par son arbre d'unités, unités archivées comprises. Un autre répertoire implémente le port :

```php
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

final readonly class Departments implements UnitDirectory
{
    public function __construct(private DepartmentRepository $departments) {}

    public function path(string $unit): array
    {
        return array_map(
            static fn (Department $department): UnitNode => new UnitNode($department->id, $department->name),
            $this->departments->pathFromRoot($unit),
        );
    }
}
```

```yaml
services:
    Phil\ReportingBundle\Application\Contract\UnitDirectory: '@App\Reporting\Departments'
```

Le rapport demande le chemin de chaque unité une fois par export. Une unité que le répertoire ne connaît pas — une unité
supprimée depuis — est rapportée seule, nommée par son identifiant.

## Le rapport

| Colonne | Valeur |
|---|---|
| Début, Fin | Le premier et le dernier jour de la période |
| Unité, Nom de l'unité | L'identifiant et le nom de l'unité ; vides et « Sans unité » pour les activités sans unité |
| Niveau | La profondeur de l'unité dans son arbre, 1 pour une racine ; 0 pour les activités sans unité |
| Activités | Le nombre d'activités |
| Heures | Les heures, avec deux décimales au plus |
| Coûts, Dépenses admissibles | Les montants, avec les décimales de la devise |
| Devise | Le code ISO 4217 de la source |

Les lignes suivent les périodes, puis l'arbre : une unité après son parent, les unités d'un parent classées par nom,
puis par identifiant ; les activités sans unité en dernier. Une unité sans activité dans une période n'a pas de ligne.

| Paramètre | Valeur | Valeur par défaut |
|---|---|---|
| `step` | `month`, `quarter` ou `year` : une ligne par unité pour chaque mois, trois mois ou année à partir du début de la période | La période entière |
| `include_below` | `true` : la ligne d'une unité compte les activités des unités en dessous, dont chacune a aussi sa ligne ; `false` : chaque unité seule | `true` |
| `unit` | L'identifiant d'une unité : seulement ses activités et celles des unités en dessous, les unités au-dessus étant écartées | Toutes les unités |
| `locale` | La langue des en-têtes, `fr` ou `fr_CA`, par le traducteur | La locale du traducteur |

Avec `include_below`, une colonne additionnée sur toutes les lignes compte une activité une fois par niveau de son unité :
additionnez les lignes du niveau 1, ou demandez `include_below: false`.

```sh
bin/console phil:reporting:export training_hours --period=last_year --format=csv
```

```php
$queue->queue(new ReportJob('training_hours', 'csv', new ReportRequest($periods->period(PeriodPreset::LastYear), [
    'step' => 'quarter',
    'unit' => $unitOfTheManager,
]), 'mail', ['manager@example.com']));
```

Le rapport est exporté dans tous les formats du bundle — CSV, TSV, JSON —, diffusé en téléchargement, planifié et livré
comme n'importe quel rapport : voir [Rapports](reports.md). Quelle unité un lecteur peut demander, c'est à l'application
de le vérifier, dans le contrôleur qui diffuse le rapport ou avant de mettre la tâche en file d'attente.

## Limites

Un rapport garde au plus 50 000 totaux — une période et une unité pour chacun — et s'arrête avec `ReportTooLarge` au-delà :
douze mois de quatre mille unités. Le pas coupe la période en 500 parties au plus, comme le fait `Period::split()`.
