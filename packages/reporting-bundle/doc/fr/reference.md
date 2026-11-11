<!-- phil-dev source-sha256=2bf4cbc8aeb9d4cbc752caea326d609187ee5e7ce2fc2b836dea79c9a6cd6e22 -->
# Référence

| Classe | Nature | Rôle |
|---|---|---|
| `Phil\ReportingBundle\PhilReportingBundle` | bundle | La configuration `phil_reporting`, la feuille de style associée |
| `Phil\ReportingBundle\Application\Contract\MetricSource`, `SeriesSource` | interface | Le port des sources de données |
| `Phil\ReportingBundle\Application\Contract\ReportDefinition` | interface | Les colonnes et les lignes d'un rapport |
| `Phil\ReportingBundle\Application\Contract\ReportFormat` | interface | Un format de fichier |
| `Phil\ReportingBundle\Application\Contract\ReportDelivery` | interface | La façon dont un fichier atteint ses destinataires |
| `Phil\ReportingBundle\Application\Contract\JobStore`, `ExportStore` | interface | Le *claim check* |
| `Phil\ReportingBundle\Application\Sources` | `final readonly class` | Les sources par clé |
| `Phil\ReportingBundle\Application\PeriodPreset` | enum | Les périodes nommées : `period()`, `previous()` |
| `Phil\ReportingBundle\Application\Periods` | `final readonly class` | Les périodes prédéfinies dans le fuseau horaire configuré |
| `Phil\ReportingBundle\Application\Dashboard\Dashboards`, `Dashboard`, `Widget` | `final readonly class` | Les tableaux de bord de la configuration |
| `Phil\ReportingBundle\Application\Dashboard\WidgetKind`, `ChartRendering` | enum | `metric`, `chart` ; `svg`, `html`, `table` |
| `Phil\ReportingBundle\Application\View\ChartDrawing` | `final readonly class` | L'image SVG et les barres |
| `Phil\ReportingBundle\Application\View\MetricReading` | `final readonly class` | Une mesure, une valeur et une variation écrites dans une locale |
| `Phil\ReportingBundle\Application\Report\Reports` | `final readonly class` | Les rapports et les formats par clé ; `write()`, `filename()` |
| `Phil\ReportingBundle\Application\Report\ReportRequest`, `ReportJob` | `final readonly class` | Ce qu'on demande à un rapport ; une tâche de la file d'attente |
| `Phil\ReportingBundle\Application\Report\StoredExport` | `final readonly class` | Un fichier du magasin d'exports |
| `Phil\ReportingBundle\Application\Report\ReportStream` | `final class` | Un flux dans lequel un rapport est écrit ou depuis lequel il est lu |
| `Phil\ReportingBundle\Application\Report\ReportRunner` | `final readonly class` | Exporte, livre et oublie une tâche gardée |
| `Phil\ReportingBundle\Application\Report\ReportSchedule`, `Schedules` | `final readonly class` | Les calendriers de la configuration |
| `Phil\ReportingBundle\Application\Message\RunReport`, `RunScheduledReport` | `final readonly class` | Les messages, des identifiants seulement |
| `Phil\ReportingBundle\Application\Event\ReportExported` | `final readonly class` | Une tâche exportée |
| `Phil\ReportingBundle\Application\Exception\NotFound`, `JobUnavailable`, `ReportTooLarge`, `InvalidReportJob`, `AccessDenied` | `final class` | Ce que refusent les services |
| `Phil\ReportingBundle\Domain\ValueObject\Period`, `Metric`, `Series`, `SeriesSet` | `final readonly class` | Les chiffres |
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
| `Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand` | `final readonly class` | `phil:reporting:export` |
| `Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin` | `final class` | Le gabarit EasyAdmin et le lien du menu |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\ReportingServices` | `final class` | Les services et les étiquettes `phil_reporting.*` |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\KeyedServicesPass` | `final class` | Les clés des services étiquetés |
