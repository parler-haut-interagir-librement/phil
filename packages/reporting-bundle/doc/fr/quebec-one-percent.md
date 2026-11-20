<!-- phil-dev source-sha256=cdc3d53314a63895f4ab0d0c138ce75cb3a88fba3ea9d111fc9aaf10da1de088 -->
# Le gabarit du 1 % de la masse salariale (Québec)

La *Loi favorisant le développement et la reconnaissance des compétences de la main-d'œuvre* du Québec (RLRQ,
chapitre D-8.3) demande à l'employeur dont la masse salariale totale d'une année civile dépasse deux millions de dollars
d'investir au moins 1 % de cette masse salariale dans des dépenses de formation admissibles, ou de verser la différence
au Fonds de développement et de reconnaissance des compétences de la main-d'œuvre. Le rapport
`training_quebec_1_percent` rassemble les chiffres de cette obligation à partir de la [source de
formation](training.md) et de la masse salariale.

Le rapport calcule ; il ne décide pas. Les dépenses admissibles, ce que comprend la masse salariale totale et la façon
de déclarer l'année sont fixés par la Loi, ses règlements et Revenu Québec : la source de formation donne le coût
admissible de chaque activité tel que l'employeur le compte, et la source de la masse salariale ou la requête donne la
masse salariale totale telle que la Loi la définit.

## Les colonnes

| Colonne | Valeur |
|---|---|
| Début, Fin | Le premier et le dernier jour de la période : une année civile pour la Loi |
| Activités, Heures | Les activités de formation de la période et leurs heures |
| Coûts | Leurs coûts |
| Dépenses admissibles | La part de leurs coûts que la Loi compte |
| Masse salariale | La masse salariale totale de la période |
| Investissement requis (1 %) | 1 % de la masse salariale, arrondi à la moitié supérieure au cent |
| Dépenses admissibles sur masse salariale (%) | Les coûts admissibles en pourcentage de la masse salariale, deux décimales |
| Écart à combler | Ce qu'il manque aux coûts admissibles pour atteindre l'investissement requis ; 0 quand ils l'atteignent |
| Masse salariale au-dessus du seuil | Si la masse salariale dépasse deux millions de dollars, ce qui rend la Loi applicable |
| Devise | Le code ISO 4217 de la source de formation |

Sans masse salariale, les cinq colonnes qui en dépendent sont vides. Une masse salariale de zéro n'exige rien et n'a pas
de pourcentage.

Toutes les unités de l'organisation comptent ensemble : l'obligation est celle de l'employeur. Le rapport de chaque unité
est [`training_hours`](training.md#le-rapport).

## La masse salariale

La masse salariale vient, dans l'ordre :

1. du paramètre `payroll` de la requête, en unités mineures — des cents —, pour un rapport d'une seule période ;
2. du `PayrollSource` de l'application, interrogé pour chaque période ;
3. de rien : les colonnes de masse salariale sont vides.

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

Une masse salariale est dans la devise de la source de formation, de 0 à 10¹⁵ unités mineures ; toute autre échoue avec
`InvalidFigures`.

## La demander

| Paramètre | Valeur | Valeur par défaut |
|---|---|---|
| `step` | `month`, `quarter` ou `year`, à partir du début de la période | La période entière |
| `payroll` | La masse salariale totale en unités mineures, un nombre entier de zéro ou plus ; refusée avec un `step` qui donne plus d'une période | La source de la masse salariale |
| `locale` | La langue des en-têtes : `fr` les écrit en français | La locale du traducteur |

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

La période prédéfinie `last_year` est une année civile dans le fuseau horaire configuré : réglez `phil_reporting.timezone`
sur `America/Toronto`, celui du Québec, pour l'année que compte la Loi. Le rapport est exporté dans tous les formats du
bundle, planifié chaque année et livré comme n'importe quel rapport : voir [Rapports](reports.md).

## Le seuil

Le seuil est le quatrième argument du service, en unités mineures : `200000000`, deux millions de dollars. Changez-le
dans la définition du service quand la Loi le change :

```yaml
services:
    Phil\ReportingBundle\Application\Training\QuebecTrainingReport:
        arguments:
            $threshold: 250000000
```

Lisez le seuil par rapport à une masse salariale en dollars : dans une autre devise, la colonne ne dit rien de la Loi.
