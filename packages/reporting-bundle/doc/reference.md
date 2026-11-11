# Reference

| Class | Kind | Role |
|---|---|---|
| `Phil\ReportingBundle\PhilReportingBundle` | bundle | The configuration `phil_reporting`, the stylesheet mapped |
| `Phil\ReportingBundle\Application\Contract\MetricSource`, `SeriesSource` | interface | The data-source port |
| `Phil\ReportingBundle\Application\Contract\ReportDefinition` | interface | A report's columns and rows |
| `Phil\ReportingBundle\Application\Contract\ReportFormat` | interface | A file format |
| `Phil\ReportingBundle\Application\Contract\ReportDelivery` | interface | How a file reaches its recipients |
| `Phil\ReportingBundle\Application\Contract\JobStore`, `ExportStore` | interface | The claim check |
| `Phil\ReportingBundle\Application\Sources` | `final readonly class` | The sources by key |
| `Phil\ReportingBundle\Application\PeriodPreset` | enum | The named periods: `period()`, `previous()` |
| `Phil\ReportingBundle\Application\Periods` | `final readonly class` | The presets in the configured time zone |
| `Phil\ReportingBundle\Application\Dashboard\Dashboards`, `Dashboard`, `Widget` | `final readonly class` | The dashboards of the configuration |
| `Phil\ReportingBundle\Application\Dashboard\WidgetKind`, `ChartRendering` | enum | `metric`, `chart`; `svg`, `html`, `table` |
| `Phil\ReportingBundle\Application\View\ChartDrawing` | `final readonly class` | The SVG image and the bars |
| `Phil\ReportingBundle\Application\View\MetricReading` | `final readonly class` | A metric, a value and a change written in a locale |
| `Phil\ReportingBundle\Application\Report\Reports` | `final readonly class` | The reports and formats by key; `write()`, `filename()` |
| `Phil\ReportingBundle\Application\Report\ReportRequest`, `ReportJob` | `final readonly class` | What a report is asked for; a job of the queue |
| `Phil\ReportingBundle\Application\Report\StoredExport` | `final readonly class` | A file of the export store |
| `Phil\ReportingBundle\Application\Report\ReportStream` | `final class` | A stream a report is written to or read from |
| `Phil\ReportingBundle\Application\Report\ReportRunner` | `final readonly class` | Exports, delivers and forgets a kept job |
| `Phil\ReportingBundle\Application\Report\ReportSchedule`, `Schedules` | `final readonly class` | The schedules of the configuration |
| `Phil\ReportingBundle\Application\Message\RunReport`, `RunScheduledReport` | `final readonly class` | The messages, identifiers only |
| `Phil\ReportingBundle\Application\Event\ReportExported` | `final readonly class` | A job exported |
| `Phil\ReportingBundle\Application\Exception\NotFound`, `JobUnavailable`, `ReportTooLarge`, `InvalidReportJob`, `AccessDenied` | `final class` | What the services refuse |
| `Phil\ReportingBundle\Domain\ValueObject\Period`, `Metric`, `Series`, `SeriesSet` | `final readonly class` | The figures |
| `Phil\ReportingBundle\Domain\Format\MetricFormat` | enum | `number`, `percent`, `currency` |
| `Phil\ReportingBundle\Domain\Exception\ReportingException` | interface | Every exception of the bundle |
| `Phil\ReportingBundle\Domain\Exception\InvalidFigures` | `final class` | Figures that cannot be shown |
| `Phil\ReportingBundle\Infrastructure\Format\CsvFormat`, `TsvFormat`, `JsonFormat` | `final readonly class` | The formats |
| `Phil\ReportingBundle\Infrastructure\Store\DirectoryJobStore`, `DirectoryExportStore` | `final readonly class` | The stores in a private directory |
| `Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore`, `InMemoryExportStore` | `final class` | The stores in memory |
| `Phil\ReportingBundle\Infrastructure\Messenger\ReportQueue` | `final readonly class` | A job kept and its message dispatched |
| `Phil\ReportingBundle\Infrastructure\Messenger\RunReportHandler`, `RunScheduledReportHandler` | `final readonly class` | The handlers |
| `Phil\ReportingBundle\Infrastructure\Scheduler\ReportScheduleProvider` | `final readonly class` | The `phil_reporting` schedule |
| `Phil\ReportingBundle\Infrastructure\Delivery\MailDelivery`, `NotificationDelivery` | `final readonly class` | The deliveries |
| `Phil\ReportingBundle\Infrastructure\Twig\Components\DashboardComponent`, `LiveDashboardComponent`, `MetricComponent`, `ChartComponent` | `final class` | The components |
| `Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess` | `final readonly class` | A dashboard given only to a viewer with its role |
| `Phil\ReportingBundle\Infrastructure\Http\ReportResponses` | `final readonly class` | A report streamed as a download |
| `Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand` | `final readonly class` | `phil:reporting:export` |
| `Phil\ReportingBundle\Infrastructure\EasyAdmin\ReportingAdmin` | `final class` | The EasyAdmin template and menu link |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\ReportingServices` | `final class` | The services and the tags `phil_reporting.*` |
| `Phil\ReportingBundle\Infrastructure\DependencyInjection\KeyedServicesPass` | `final class` | The keys of the tagged services |
