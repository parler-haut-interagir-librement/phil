<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\DependencyInjection;

use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Application\Dashboard\Dashboards;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Report\ReportRunner;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\Schedules;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Application\View\MetricReading;
use Phil\ReportingBundle\Infrastructure\Console\ExportReportCommand;
use Phil\ReportingBundle\Infrastructure\Delivery\MailDelivery;
use Phil\ReportingBundle\Infrastructure\Delivery\NotificationDelivery;
use Phil\ReportingBundle\Infrastructure\Format\CsvFormat;
use Phil\ReportingBundle\Infrastructure\Format\JsonFormat;
use Phil\ReportingBundle\Infrastructure\Format\TsvFormat;
use Phil\ReportingBundle\Infrastructure\Http\ReportResponses;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Infrastructure\Messenger\ReportQueue;
use Phil\ReportingBundle\Infrastructure\Messenger\RunReportHandler;
use Phil\ReportingBundle\Infrastructure\Messenger\RunScheduledReportHandler;
use Phil\ReportingBundle\Infrastructure\Scheduler\ReportScheduleProvider;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryExportStore;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryJobStore;
use Phil\ReportingBundle\Infrastructure\Twig\Components\ChartComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\DashboardComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\LiveDashboardComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\MetricComponent;
use Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bundle's services: the sources, reports, formats and deliveries found by their tags, the periods, the
 * dashboards, the stores of the claim check and the runner; the components, the queue and its handlers, the schedule,
 * the deliveries, the command and the downloads when their Symfony packages are there.
 *
 * @phpstan-type Config array{timezone: string, chart: array{width: int, height: int}, max_rows: int, store: string, directory: string, keep_delivered: bool, mail: array{enabled: bool, from: string}, notification: array{enabled: bool, channels: list<string>}, dashboards: array<string, mixed>, schedules: array<string, mixed>}
 */
final class ReportingServices
{
    public const string METRIC_SOURCE_TAG = 'phil_reporting.metric_source';

    public const string SERIES_SOURCE_TAG = 'phil_reporting.series_source';

    public const string REPORT_TAG = 'phil_reporting.report';

    public const string FORMAT_TAG = 'phil_reporting.format';

    public const string DELIVERY_TAG = 'phil_reporting.delivery';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        foreach ([
            MetricSource::class => self::METRIC_SOURCE_TAG,
            SeriesSource::class => self::SERIES_SOURCE_TAG,
            ReportDefinition::class => self::REPORT_TAG,
            ReportFormat::class => self::FORMAT_TAG,
            ReportDelivery::class => self::DELIVERY_TAG,
        ] as $interface => $tag) {
            $container->registerForAutoconfiguration($interface)->addTag($tag);
        }

        foreach ([CsvFormat::class, TsvFormat::class, JsonFormat::class] as $format) {
            $container->register($format, $format)->addTag(self::FORMAT_TAG, ['key' => $format::key()]);
        }

        $container->register(Periods::class, Periods::class)
            ->setArguments([new Reference('clock'), $config['timezone']])
            ->setPublic(true);
        $container->register(Sources::class, Sources::class)
            ->setArguments([self::locator(self::METRIC_SOURCE_TAG), self::locator(self::SERIES_SOURCE_TAG)])
            ->setPublic(true);
        $container->register(Dashboards::class, Dashboards::class)
            ->setFactory([Dashboards::class, 'fromConfig'])
            ->setArguments([$config['dashboards']])
            ->setPublic(true);
        $container->register(ChartDrawing::class, ChartDrawing::class)
            ->setArguments([$config['chart']['width'], $config['chart']['height']]);
        $container->register(MetricReading::class, MetricReading::class);
        $container->register(Reports::class, Reports::class)
            ->setArguments([self::locator(self::REPORT_TAG), self::locator(self::FORMAT_TAG), $config['max_rows']])
            ->setPublic(true);
        $container->register(Schedules::class, Schedules::class)
            ->setFactory([Schedules::class, 'fromConfig'])
            ->setArguments([$config['schedules'], new Reference(Periods::class)])
            ->setPublic(true);

        self::stores($container, $config['store'], $config['directory']);

        $container->register(ReportRunner::class, ReportRunner::class)
            ->setArguments([
                new Reference(Reports::class),
                new Reference(JobStore::class),
                new Reference(ExportStore::class),
                self::locator(self::DELIVERY_TAG),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['keep_delivered'],
            ])
            ->setPublic(true);

        self::deliveries($container, $config['mail'], $config['notification']);
        self::components($container);
        self::messenger($container, [] !== $config['schedules']);

        if (class_exists(Command::class)) {
            $container->register(ExportReportCommand::class, ExportReportCommand::class)
                ->setArguments([new Reference(Reports::class), new Reference(Periods::class)])
                ->addTag('console.command');
        }

        if (class_exists(StreamedResponse::class)) {
            $container->register(ReportResponses::class, ReportResponses::class)
                ->setArguments([new Reference(Reports::class)])
                ->setPublic(true);
        }
    }

    private static function locator(string $tag): ServiceLocatorArgument
    {
        return new ServiceLocatorArgument(new TaggedIteratorArgument($tag, 'key', true));
    }

    private static function stores(ContainerBuilder $container, string $store, string $directory): void
    {
        if ('memory' === $store) {
            $container->register(InMemoryJobStore::class, InMemoryJobStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->register(InMemoryExportStore::class, InMemoryExportStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(JobStore::class, InMemoryJobStore::class)->setPublic(true);
            $container->setAlias(ExportStore::class, InMemoryExportStore::class)->setPublic(true);

            return;
        }

        $container->register(DirectoryJobStore::class, DirectoryJobStore::class)->setArguments([$directory.'/jobs']);
        $container->register(DirectoryExportStore::class, DirectoryExportStore::class)->setArguments([$directory.'/exports']);
        $container->setAlias(JobStore::class, DirectoryJobStore::class)->setPublic(true);
        $container->setAlias(ExportStore::class, DirectoryExportStore::class)->setPublic(true);
    }

    /**
     * @param array{enabled: bool, from: string}           $mail
     * @param array{enabled: bool, channels: list<string>} $notification
     */
    private static function deliveries(ContainerBuilder $container, array $mail, array $notification): void
    {
        if ($mail['enabled'] && interface_exists(TransportInterface::class)) {
            $container->register(MailDelivery::class, MailDelivery::class)
                ->setArguments([new Reference('mailer.transports'), new Reference(Reports::class), $mail['from']])
                ->addTag(self::DELIVERY_TAG, ['key' => MailDelivery::key()]);
        }

        if ($notification['enabled'] && interface_exists(NotifierInterface::class)) {
            $container->register(NotificationDelivery::class, NotificationDelivery::class)
                ->setArguments([new Reference('notifier'), new Reference(Reports::class), $notification['channels']])
                ->addTag(self::DELIVERY_TAG, ['key' => NotificationDelivery::key()]);
        }
    }

    private static function components(ContainerBuilder $container): void
    {
        if (!class_exists(AsTwigComponent::class)) {
            return;
        }

        $container->register(DashboardAccess::class, DashboardAccess::class)
            ->setArguments([new Reference(Dashboards::class), new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(MetricComponent::class, MetricComponent::class)
            ->setArguments([new Reference(Sources::class), new Reference(Periods::class), new Reference(MetricReading::class)])
            ->setAutoconfigured(true);
        $container->register(ChartComponent::class, ChartComponent::class)
            ->setArguments([new Reference(Sources::class), new Reference(Periods::class), new Reference(ChartDrawing::class), new Reference(MetricReading::class)])
            ->setAutoconfigured(true);
        $container->register(DashboardComponent::class, DashboardComponent::class)
            ->setArguments([new Reference(DashboardAccess::class), new Reference(Periods::class)])
            ->setAutoconfigured(true);

        if (class_exists(AsLiveComponent::class)) {
            $container->register(LiveDashboardComponent::class, LiveDashboardComponent::class)
                ->setArguments([new Reference(DashboardAccess::class), new Reference(Periods::class)])
                ->setAutoconfigured(true);
        }
    }

    private static function messenger(ContainerBuilder $container, bool $scheduled): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            return;
        }

        $container->register(ReportQueue::class, ReportQueue::class)
            ->setArguments([new Reference(JobStore::class), new Reference(MessageBusInterface::class)])
            ->setPublic(true);
        $container->register(RunReportHandler::class, RunReportHandler::class)
            ->setArguments([new Reference(ReportRunner::class), new Reference(JobStore::class)])
            ->addTag('messenger.message_handler');
        $container->register(RunScheduledReportHandler::class, RunScheduledReportHandler::class)
            ->setArguments([new Reference(Schedules::class), new Reference(ReportQueue::class)])
            ->addTag('messenger.message_handler');

        if ($scheduled && interface_exists(ScheduleProviderInterface::class)) {
            $container->register(ReportScheduleProvider::class, ReportScheduleProvider::class)
                ->setArguments([new Reference(Schedules::class)])
                ->addTag('scheduler.schedule_provider', ['name' => ReportScheduleProvider::NAME]);
        }
    }
}
