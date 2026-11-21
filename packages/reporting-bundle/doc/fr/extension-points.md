<!-- phil-dev source-sha256=824b278b144f93fecb6cc33239da98d545bd843cd13f93fe29709af54061cffc -->
# Points d'extension

Chaque port se trouve dans `Phil\ReportingBundle\Application\Contract`. Jusqu'à la première version, chacun peut encore
changer ; il est stable à partir de la 1.0.

## `MetricSource` et `SeriesSource`

Le port des sources de données : la façon dont un bundle ou l'application fournit un chiffre ou une série aux tableaux
de bord. Voir [Sources](sources.md).

```php
interface MetricSource
{
    public static function key(): string;

    public function title(): string;

    public function metric(Period $period): Metric;
}

interface SeriesSource
{
    public static function key(): string;

    public function title(): string;

    public function series(Period $period): SeriesSet;
}
```

## `ReportDefinition`

Un rapport : ses colonnes et ses lignes pour une requête. Voir [Rapports](reports.md).

## `ChartedReport`

Un rapport qui a un graphique, que la livraison `mail` met dans son courriel sous forme d'image PNG : `chart()` donne un
`ReportChart`, la clé d'une source de séries, un `ChartType` et un titre. Voir
[Rapports](reports.md#le-graphique-dun-rapport-dans-son-courriel).

## `TrainingSource`, `UnitDirectory` et `PayrollSource`

Les ports des rapports de formation, chacun un service unique que l'application aliase : les activités de son système
d'apprentissage, dans une devise ; le chemin d'une unité organisationnelle depuis sa racine ; la masse salariale d'une
période. Sans `TrainingSource`, les rapports de formation ne sont pas enregistrés ; les deux autres sont facultatifs.
Voir [Heures et coûts de formation](training.md) et [Le gabarit du 1 % de la masse salariale](quebec-one-percent.md).

```php
interface TrainingSource
{
    public function currency(): string;

    /** @return iterable<TrainingActivity> */
    public function activities(Period $period): iterable;
}

interface UnitDirectory
{
    /** @return list<UnitNode> */
    public function path(string $unit): array;
}

interface PayrollSource
{
    public function payroll(Period $period): ?int;
}
```

## `ReportFormat`

Un format de fichier en plus de CSV, TSV et JSON : sa clé, son type de média et son extension, et la façon dont il
écrit les titres et les lignes dans un `ReportStream` à mesure qu'ils arrivent. Un format qui a besoin de toutes les
lignes avant d'écrire — un tableur — tient compte de la borne de `max_rows`.

```php
final readonly class MarkdownFormat implements ReportFormat
{
    public static function key(): string { return 'md'; }

    public function mediaType(): string { return 'text/markdown; charset=UTF-8'; }

    public function extension(): string { return 'md'; }

    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        $stream->write('| '.implode(' | ', $columns)." |\n");

        foreach ($rows as $row) {
            $stream->write('| '.implode(' | ', array_map(strval(...), $row))." |\n");
        }
    }
}
```

## `ReportDelivery`

Une façon dont le fichier atteint ses destinataires en plus de `mail` et `notification` : un disque partagé, un
téléversement dans un clavardage. Elle reçoit la tâche, le `StoredExport` et le fichier sous forme de `ReportStream` lu
depuis son début, et s'exécute dans le *worker*. Une exception qu'elle lève fait l'objet d'une nouvelle tentative par
Messenger, la tâche étant gardée.

## `JobStore` et `ExportStore`

Les deux moitiés du *claim check*. Faites-en des alias vers des services de l'application pour garder les tâches et
les fichiers ailleurs que dans le répertoire local — une table de base de données, un stockage d'objets partagé par
plusieurs machines :

```yaml
services:
    Phil\ReportingBundle\Application\Contract\JobStore: '@app.reporting.job_store'
    Phil\ReportingBundle\Application\Contract\ExportStore: '@app.reporting.export_store'
```

`ReportJob::toArray()` et `ReportJob::fromArray()` donnent à un magasin de tâches le JSON à garder et le relisent. Un
magasin lève `JobUnavailable` pour un identifiant sous lequel il ne contient rien, et ignore le retrait d'un tel
identifiant. Un magasin d'exports lit le fichier depuis un `ReportStream` et le rend sous forme d'un autre par `open()`,
que l'appelant ferme ; `ReportStream::of()` enveloppe ce que renvoie `fopen()`. Un magasin qui ne peut pas écrire un
fichier en entier lève `ExportFailed` et ne garde rien : la tâche reste, pour que Messenger réessaie.
`ReportStream::close()` lève `ExportFailed` quand le flux ne peut pas être vidé et fermé.

## Le rasterizer

Les graphiques PNG sont dessinés par le service `Phil\SvgChart\Contract\Rasterizer` : `ResvgRasterizer` quand
l'extension `resvg` est chargée et que `png.fonts` liste un fichier. Quand aucune police n'est listée, une application
qui dessine les images PNG autrement enregistre son propre service sous cette interface, et les graphiques, les
téléchargements et les courriels l'utilisent.

## Enregistrement

Avec l'autoconfiguration, un service qui implémente l'une des interfaces `MetricSource`, `SeriesSource`,
`ReportDefinition`, `ReportFormat` ou `ReportDelivery` reçoit l'étiquette `phil_reporting.metric_source`,
`phil_reporting.series_source`, `phil_reporting.report`, `phil_reporting.format` ou `phil_reporting.delivery`, et il
est trouvé par sa `key()`, ou par la `key` que nomme son étiquette. Un service étiqueté qui n'implémente pas
l'interface de son étiquette arrête la compilation du conteneur.

## L'événement

`ReportExported` est réparti par le répartiteur PSR-14, s'il y en a un, une fois une tâche exportée : les identifiants
de la tâche et du fichier, et s'il a été livré.

## Les gabarits

Les gabarits des composants sont `@PhilReporting/components/Dashboard.html.twig`, `Metric.html.twig`,
`Chart.html.twig` et `_table.html.twig` ; remplacez-les sous `templates/bundles/PhilReportingBundle/` comme ceux de
n'importe quel bundle. Les libellés sont dans le domaine de traduction `phil_reporting`.
