<!-- phil-dev source-sha256=c6fab83d75b1e29d8b52d7d365b76217dbd8ca2641c13bcf9e384886c9cde03a -->
# Sources

Une source est la façon dont un bundle, ou l'application, place un chiffre sur un tableau de bord. Le bundle ne connaît
aucun chiffre qui lui soit propre.

## Une mesure

```php
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class Revenue implements MetricSource
{
    public function __construct(private Payments $payments) {}

    public static function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Revenue';
    }

    public function metric(Period $period): Metric
    {
        return new Metric($this->payments->sumBetween($period->start, $period->end), MetricFormat::Currency, 'CAD', 2);
    }
}
```

Une `Metric` est un nombre fini, son `MetricFormat` — `Number`, `Percent` pour une part de un, `Currency` avec son code
ISO 4217 —, et de 0 à 6 décimales. Un *widget* avec `compare` interroge la source une deuxième fois, pour la période
précédente, et affiche la variation sous forme de direction et de pourcentage du chiffre précédent ; aucune variation
quand le chiffre précédent est nul.

## Séries

```php
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;

final readonly class Visits implements SeriesSource
{
    public function __construct(private VisitLog $log) {}

    public static function key(): string
    {
        return 'visits';
    }

    public function title(): string
    {
        return 'Visits';
    }

    public function series(Period $period): SeriesSet
    {
        $days = $period->split('1 day');

        return new SeriesSet(
            array_map(static fn (Period $day): string => $day->start->format('M j'), $days),
            [new Series('Visits', array_map(fn (Period $day): ?float => $this->log->countBetween($day->start, $day->end), $days))],
        );
    }
}
```

Un `SeriesSet` contient des libellés et des séries, chaque série ayant une valeur par libellé, `null` là où elle n'en a
pas. Il est borné à 500 libellés, 24 séries et des libellés de 200 caractères, les limites de `phil/svg-chart` : une
source ne coûte ainsi pas plus à dessiner qu'un grand graphique honnête.

## Périodes

Une `Period` va de son début inclus à sa fin exclue. `contains()` dit si un moment y tombe ; `split('1 day')`,
`split('1 week')` ou `split('1 month')` la découpe en tranches consécutives, la dernière coupée à la fin, au plus 500.
Un pas qui n'avance pas est refusé avec `InvalidFigures`.

Les périodes prédéfinies sont comptées en journées entières du fuseau horaire configuré, à partir du service `clock` :

| Période prédéfinie | Période | La période précédente |
|---|---|---|
| `today`, `yesterday` | Le jour | Le jour d'avant |
| `last_7_days`, `last_30_days` | Les jours qui se terminent aujourd'hui, aujourd'hui compris | Le même nombre de jours avant |
| `this_month`, `last_month` | Le mois civil | Le mois d'avant |
| `last_12_months` | Douze mois civils qui se terminent par celui-ci | Les douze d'avant |
| `this_year`, `last_year` | L'année civile | L'année d'avant |

## Enregistrement

Avec l'autoconfiguration, un service qui implémente `MetricSource` ou `SeriesSource` reçoit l'étiquette
`phil_reporting.metric_source` ou `phil_reporting.series_source`, et il est trouvé par la clé que renvoie sa `key()`.
Une étiquette qui nomme sa propre `key` l'emporte, ce qui enregistre une même classe sous plusieurs clés :

```yaml
services:
    app.revenue_eur:
        class: App\Reporting\Revenue
        tags: [{ name: phil_reporting.metric_source, key: revenue_eur }]
```

Une source n'est construite que lorsqu'un *widget* la demande. Elle ne garde aucun état d'une requête à l'autre : elle
lit ce qu'elle mesure chaque fois qu'on l'interroge.
