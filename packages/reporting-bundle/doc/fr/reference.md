<!-- phil-dev source-sha256=fdb40274ab7e95ca709b96f2d4224f2da2577b1a3b44a22a888b6063c2e8232b -->
# Référence

| Classe | Nature | Rôle |
|---|---|---|
| `Phil\ReportingBundle\PhilReportingBundle` | bundle | La configuration `phil_reporting`, la feuille de style associée |
| `Phil\ReportingBundle\Application\Contract\MetricSource`, `SeriesSource` | interface | Le port des sources de données |
| `Phil\ReportingBundle\Application\Contract\ReportDefinition` | interface | Les colonnes et les lignes d'un rapport |
| `Phil\ReportingBundle\Application\Contract\ChartedReport` | interface | Un rapport qui a un graphique, pour son courriel |
| `Phil\ReportingBundle\Application\Contract\ReportFormat` | interface | Un format de fichier |
| `Phil\ReportingBundle\Application\Contract\ReportDelivery` | interface | La façon dont un fichier atteint ses destinataires |
| `Phil\ReportingBundle\Application\Contract\JobStore`, `ExportStore` | interface | Le *claim check* |
| `Phil\ReportingBundle\Application\Contract\TrainingSource`, `UnitDirectory`, `PayrollSource` | interface | Les ports des rapports de formation |
| `Phil\ReportingBundle\Application\Training\TrainingHoursReport` | `final readonly class` | `training_hours`, par période et unité organisationnelle |
| `Phil\ReportingBundle\Application\Training\QuebecTrainingReport` | `final readonly class` | `training_quebec_1_percent`, le gabarit du 1 % de la masse salariale |
| `Phil\ReportingBundle\Application\Training\TrainingLedger` | `final readonly class` | Les activités totalisées par période et unité, dans l'ordre de l'arbre |
| `Phil\ReportingBundle\Application\Training\TrainingTotals` | `final readonly class` | Les totaux d'une période et d'une unité |
| `Phil\ReportingBundle\Application\Training\TrainingStep` | enum | `month`, `quarter`, `year` |
| `Phil\ReportingBundle\Application\Training\TrainingHeadings` | `final readonly class` | Les titres et les en-têtes, traduits |
| `Phil\ReportingBundle\Application\Training\TrainingParameters` | `final class` | Les paramètres des rapports de formation, vérifiés |
| `Phil\ReportingBundle\Application\Training\CurrencyAmounts` | `final readonly class` | Des unités mineures écrites avec les décimales d'une devise |
| `Phil\ReportingBundle\Application\Sources` | `final readonly class` | Les sources par clé |
| `Phil\ReportingBundle\Application\PeriodPreset` | enum | Les périodes nommées : `period()`, `previous()` |
| `Phil\ReportingBundle\Application\Periods` | `final readonly class` | Les périodes prédéfinies dans le fuseau horaire configuré |
| `Phil\ReportingBundle\Application\Dashboard\Dashboards`, `Dashboard`, `Widget` | `final readonly class` | Les tableaux de bord de la configuration |
| `Phil\ReportingBundle\Application\Dashboard\WidgetKind`, `ChartRendering` | enum | `metric`, `chart` ; `svg`, `html`, `table` |
| `Phil\ReportingBundle\Application\View\ChartDrawing` | `final readonly class` | L'image SVG, les barres, et l'image PNG là où un rasterizer est donné |
| `Phil\ReportingBundle\Application\View\MetricReading` | `final readonly class` | Une mesure, une valeur et une variation écrites dans une locale |
| `Phil\ReportingBundle\Application\Report\Reports` | `final readonly class` | Les rapports et les formats par clé ; `write()`, `filename()` |
| `Phil\ReportingBundle\Application\Report\ReportRequest`, `ReportJob` | `final readonly class` | Ce qu'on demande à un rapport ; une tâche de la file d'attente |
| `Phil\ReportingBundle\Application\Report\StoredExport` | `final readonly class` | Un fichier du magasin d'exports |
| `Phil\ReportingBundle\Application\Report\ReportChart` | `final readonly class` | Le graphique d'un rapport : une source de séries, une sorte de graphique, un titre |
| `Phil\ReportingBundle\Application\Report\ReportStream` | `final class` | Un flux dans lequel un rapport est écrit ou depuis lequel il est lu |
| `Phil\ReportingBundle\Application\Report\ReportRunner` | `final readonly class` | Exporte, livre et oublie une tâche gardée |
| `Phil\ReportingBundle\Application\Report\ReportSchedule`, `Schedules` | `final readonly class` | Les calendriers de la configuration |
| `Phil\ReportingBundle\Application\Message\RunReport`, `RunScheduledReport` | `final readonly class` | Les messages, des identifiants seulement |
| `Phil\ReportingBundle\Application\Event\ReportExported` | `final readonly class` | Une tâche exportée |
| `Phil\ReportingBundle\Application\Exception\NotFound`, `JobUnavailable`, `ReportTooLarge`, `InvalidReportJob`, `AccessDenied` | `final class` | Ce que refusent les services |
| `Phil\ReportingBundle\Application\Exception\ExportFailed` | `final class` | Un fichier non écrit en entier ; nouvelle tentative |
| `Phil\ReportingBundle\Application\Exception\PngUnavailable` | `final class` | Un PNG demandé là où aucun n'est dessiné |
| `Phil\ReportingBundle\Domain\ValueObject\Period`, `Metric`, `Series`, `SeriesSet` | `final readonly class` | Les chiffres |
| `Phil\ReportingBundle\Domain\Training\TrainingActivity`, `UnitNode` | `final readonly class` | Une activité d'un système d'apprentissage ; une unité sur un chemin |
| `Phil\ReportingBundle\Domain\Format\MetricFormat` | enum | `number`, `percent`, `currency` |
| `Phil\ReportingBundle\Domain\Exception\ReportingException` | interface | Chaque exception du bundle |
| `Phil\ReportingBundle\Domain\Exception\InvalidFigures` | `final class` | Des chiffres qui ne peuvent pas être affichés |
| `Phil\ReportingBundle\Infrastructure\Format\CsvFormat`, `TsvFormat`, `JsonFormat` | `final readonly class` | Les formats |
| `Phil\ReportingBundle\Infrastructure\Store\DirectoryJobStore`, `DirectoryExportStore` | `final readonly class` | Les magasins dans un répertoire privé |
| `Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore`, `InMemoryExportStore` | `final class` | Les magasins en mémoire |
| `Phil\ReportingBundle\Infrastructure\Messenger\ReportQueue` | `final readonly class` | Une tâche gardée et son message réparti |
| `Phil\ReportingBundle\Infrastructure\Messenger\RunReportHandler`, `RunScheduledReportHandler` | `final readonly class` | Les *handlers* |
| `Phil\ReportingBundle\Infrastructure\Scheduler\ReportScheduleProvider` | `final readonly class` | Le calendrier `phil_reporting` |
| `Phil\ReportingBundle\Infrastructure\Delivery\MailDelivery`, `NotificationDelivery` | `final readonly class` | Les livraisons |
| `Phil\ReportingBundle\Infrastructure\Twig\Components\DashboardComponent`, `LiveDashboardComponent`, `MetricComponent`, `ChartComponent` | `final class` | Les composants |
| `Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess` | `final readonly class` | Un tableau de bord donné seulement à un lecteur qui a son rôle |
| `Phil\ReportingBundle\Infrastructure\Http\ReportResponses` | `final readonly class` | Un rapport diffusé en téléchargement |
| `Phil\ReportingBundle\Infrastructure\Http\ChartResponses` | `final readonly class` | Le graphique d'une source de séries en téléchargement PNG |
| `Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand` | `final readonly class` | `phil:reporting:export` |
| `Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin` | `final class` | Le gabarit EasyAdmin et le lien du menu |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\ReportingServices` | `final class` | Les services et les étiquettes `phil_reporting.*` |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\KeyedServicesPass` | `final class` | Les clés des services étiquetés |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\TrainingReportsPass` | `final class` | Les rapports de formation écartés sans `TrainingSource` |
