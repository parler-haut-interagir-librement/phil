<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit;

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
use Phil\ReportingBundle\Infrastructure\DependencyInjection\KeyedServicesPass;
use Phil\ReportingBundle\Infrastructure\DependencyInjection\ReportingServices;
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
use Phil\ReportingBundle\PhilReportingBundle;
use Phil\ReportingBundle\Tests\Unit\Fixture\FrameworkStub;
use Phil\ReportingBundle\Tests\Unit\Fixture\NullBus;
use Phil\ReportingBundle\Tests\Unit\Fixture\OrdersSource;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingDelivery;
use Phil\ReportingBundle\Tests\Unit\Fixture\SalesReport;
use Phil\ReportingBundle\Tests\Unit\Fixture\VisitsSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PhilReportingBundle::class)]
#[CoversClass(ReportingServices::class)]
#[CoversClass(KeyedServicesPass::class)]
final class PhilReportingBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_reporting', $this->extension()->getAlias());
        self::assertSame([
            'service_container',
            CsvFormat::class, TsvFormat::class, JsonFormat::class,
            Periods::class, Sources::class, Dashboards::class, ChartDrawing::class, MetricReading::class, Reports::class, Schedules::class,
            DirectoryJobStore::class, DirectoryExportStore::class,
            ReportRunner::class,
            DashboardAccess::class, MetricComponent::class, ChartComponent::class, DashboardComponent::class, LiveDashboardComponent::class,
            ReportQueue::class, RunReportHandler::class, RunScheduledReportHandler::class,
            ExportReportCommand::class, ReportResponses::class,
        ], array_keys($container->getDefinitions()));

        foreach ([CsvFormat::class => 'csv', TsvFormat::class => 'tsv', JsonFormat::class => 'json'] as $format => $key) {
            self::assertSame([['key' => $key]], $container->getDefinition($format)->getTag('phil_reporting.format'));
        }

        self::assertEquals([new Reference('clock'), 'UTC'], $container->getDefinition(Periods::class)->getArguments());
        self::assertEquals([$this->locator('phil_reporting.metric_source'), $this->locator('phil_reporting.series_source')], $container->getDefinition(Sources::class)->getArguments());
        self::assertSame([[Dashboards::class, 'fromConfig'], [[]]], [$container->getDefinition(Dashboards::class)->getFactory(), $container->getDefinition(Dashboards::class)->getArguments()]);
        self::assertSame([640, 320], $container->getDefinition(ChartDrawing::class)->getArguments());
        self::assertEquals([$this->locator('phil_reporting.report'), $this->locator('phil_reporting.format'), 100_000], $container->getDefinition(Reports::class)->getArguments());
        self::assertEquals([[Schedules::class, 'fromConfig'], [[], new Reference(Periods::class)]], [$container->getDefinition(Schedules::class)->getFactory(), $container->getDefinition(Schedules::class)->getArguments()]);
        self::assertSame(['%kernel.project_dir%/var/phil_reporting/jobs'], $container->getDefinition(DirectoryJobStore::class)->getArguments());
        self::assertSame(['%kernel.project_dir%/var/phil_reporting/exports'], $container->getDefinition(DirectoryExportStore::class)->getArguments());
        self::assertSame([DirectoryJobStore::class, true], [(string) $container->getAlias(JobStore::class), $container->getAlias(JobStore::class)->isPublic()]);
        self::assertSame([DirectoryExportStore::class, true], [(string) $container->getAlias(ExportStore::class), $container->getAlias(ExportStore::class)->isPublic()]);
        self::assertEquals([
            new Reference(Reports::class),
            new Reference(JobStore::class),
            new Reference(ExportStore::class),
            $this->locator('phil_reporting.delivery'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            false,
        ], $container->getDefinition(ReportRunner::class)->getArguments());
        self::assertEquals([new Reference(Dashboards::class), new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(DashboardAccess::class)->getArguments());
        self::assertEquals([new Reference(Sources::class), new Reference(Periods::class), new Reference(MetricReading::class)], $container->getDefinition(MetricComponent::class)->getArguments());
        self::assertEquals([new Reference(Sources::class), new Reference(Periods::class), new Reference(ChartDrawing::class), new Reference(MetricReading::class)], $container->getDefinition(ChartComponent::class)->getArguments());

        foreach ([DashboardComponent::class, LiveDashboardComponent::class] as $component) {
            self::assertEquals([new Reference(DashboardAccess::class), new Reference(Periods::class)], $container->getDefinition($component)->getArguments());
        }

        foreach ([MetricComponent::class, ChartComponent::class, DashboardComponent::class, LiveDashboardComponent::class] as $component) {
            self::assertTrue($container->getDefinition($component)->isAutoconfigured(), $component);
        }

        self::assertEquals([new Reference(JobStore::class), new Reference(MessageBusInterface::class)], $container->getDefinition(ReportQueue::class)->getArguments());
        self::assertEquals([new Reference(ReportRunner::class), new Reference(JobStore::class)], $container->getDefinition(RunReportHandler::class)->getArguments());
        self::assertEquals([new Reference(Schedules::class), new Reference(ReportQueue::class)], $container->getDefinition(RunScheduledReportHandler::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(RunReportHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([[]], $container->getDefinition(RunScheduledReportHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(Reports::class), new Reference(Periods::class)], $container->getDefinition(ExportReportCommand::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ExportReportCommand::class)->getTag('console.command'));
        self::assertEquals([new Reference(Reports::class)], $container->getDefinition(ReportResponses::class)->getArguments());

        foreach ([Periods::class, Sources::class, Dashboards::class, Reports::class, Schedules::class, ReportRunner::class, ReportQueue::class, ReportResponses::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        foreach ([CsvFormat::class, ChartDrawing::class, MetricReading::class, DirectoryJobStore::class, DashboardAccess::class, RunReportHandler::class, ExportReportCommand::class] as $private) {
            self::assertFalse($container->getDefinition($private)->isPublic(), $private);
        }

        foreach ([
            MetricSource::class => 'phil_reporting.metric_source',
            SeriesSource::class => 'phil_reporting.series_source',
            ReportDefinition::class => 'phil_reporting.report',
            ReportFormat::class => 'phil_reporting.format',
            ReportDelivery::class => 'phil_reporting.delivery',
        ] as $interface => $tag) {
            self::assertSame([$tag => [[]]], $container->getAutoconfiguredInstanceof()[$interface]->getTags());
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'timezone' => 'America/Toronto',
            'chart' => ['width' => 800, 'height' => 400],
            'max_rows' => 50,
            'store' => 'memory',
            'keep_delivered' => true,
            'mail' => ['from' => 'reports@example.com'],
            'notification' => ['channels' => ['chat/slack']],
            'dashboards' => ['sales' => ['title' => 'Sales', 'periods' => ['this_month', 'this_month', 'last_month'], 'period' => 'last_month', 'widgets' => [['metric' => 'orders'], ['series' => 'visits', 'chart' => 'line', 'render' => 'table']]]],
            'schedules' => ['monthly' => ['report' => 'sales', 'every' => '1 month']],
        ]);

        self::assertSame('America/Toronto', $container->getDefinition(Periods::class)->getArgument(1));
        self::assertSame([800, 400], $container->getDefinition(ChartDrawing::class)->getArguments());
        self::assertSame(50, $container->getDefinition(Reports::class)->getArgument(2));
        self::assertTrue($container->getDefinition(ReportRunner::class)->getArgument(5));
        self::assertSame([InMemoryJobStore::class, true], [(string) $container->getAlias(JobStore::class), $container->getAlias(JobStore::class)->isPublic()]);
        self::assertSame([InMemoryExportStore::class, true], [(string) $container->getAlias(ExportStore::class), $container->getAlias(ExportStore::class)->isPublic()]);
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryJobStore::class)->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryExportStore::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(DirectoryJobStore::class));
        self::assertEquals([new Reference('mailer.transports'), new Reference(Reports::class), 'reports@example.com'], $container->getDefinition(MailDelivery::class)->getArguments());
        self::assertSame([['key' => 'mail']], $container->getDefinition(MailDelivery::class)->getTag('phil_reporting.delivery'));
        self::assertEquals([new Reference('notifier'), new Reference(Reports::class), ['chat/slack']], $container->getDefinition(NotificationDelivery::class)->getArguments());
        self::assertSame([['key' => 'notification']], $container->getDefinition(NotificationDelivery::class)->getTag('phil_reporting.delivery'));
        self::assertEquals([new Reference(Schedules::class)], $container->getDefinition(ReportScheduleProvider::class)->getArguments());
        self::assertSame([['name' => 'phil_reporting']], $container->getDefinition(ReportScheduleProvider::class)->getTag('scheduler.schedule_provider'));
        self::assertSame([[
            'monthly' => ['report' => 'sales', 'every' => '1 month', 'format' => 'csv', 'from' => null, 'period' => 'last_month', 'delivery' => null, 'recipients' => [], 'parameters' => []],
        ]], \array_slice($container->getDefinition(Schedules::class)->getArguments(), 0, 1));
        self::assertSame(['sales' => [
            'title' => 'Sales',
            'periods' => ['this_month', 'last_month'],
            'period' => 'last_month',
            'widgets' => [
                ['metric' => 'orders', 'series' => null, 'title' => '', 'compare' => false, 'chart' => 'bar', 'render' => 'svg'],
                ['series' => 'visits', 'chart' => 'line', 'render' => 'table', 'metric' => null, 'title' => '', 'compare' => false],
            ],
            'role' => null,
        ]], $container->getDefinition(Dashboards::class)->getArgument(0));

        $only = $this->load(['dashboards' => ['d' => ['title' => 'D', 'period' => 'today']]]);
        self::assertEquals(['d' => ['title' => 'D', 'role' => null, 'period' => 'today', 'periods' => ['today'], 'widgets' => []]], $only->getDefinition(Dashboards::class)->getArgument(0));
        self::assertFalse($only->hasDefinition(MailDelivery::class));
        self::assertFalse($only->hasDefinition(NotificationDelivery::class));
        self::assertFalse($only->hasDefinition(ReportScheduleProvider::class));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([
            'is not a time zone identifier' => ['timezone' => 'Mars/Olympus'],
            'is not a time zone identifier ' => ['timezone' => 12],
            'The value 99 is too small' => ['chart' => ['width' => 99]],
            'The value 4001 is too big' => ['chart' => ['width' => 4001]],
            'The value 99 is too small ' => ['chart' => ['height' => 99]],
            'The value 4001 is too big ' => ['chart' => ['height' => 4001]],
            'The value 0 is too small' => ['max_rows' => 0],
            'The value 10000001 is too big' => ['max_rows' => 10_000_001],
            'The value "redis" is not allowed' => ['store' => 'redis'],
            'cannot contain an empty value' => ['directory' => ''],
            'The "mail" delivery needs its "from" address.' => ['mail' => ['enabled' => true]],
            'A widget names either a "metric" or a "series" source.' => ['dashboards' => ['d' => ['title' => 'D', 'widgets' => [['metric' => 'a', 'series' => 'b']]]]],
            'A widget names either a "metric" or a "series" source. ' => ['dashboards' => ['d' => ['title' => 'D', 'widgets' => [['title' => 'nothing']]]]],
            "A dashboard's period is among the periods it offers." => ['dashboards' => ['d' => ['title' => 'D', 'period' => 'today', 'periods' => ['this_month']]]],
            'A dashboard is named by 1 to 64 letters, digits, dots, dashes or underscores.' => ['dashboards' => ['a b' => ['title' => 'D']]],
            'A schedule is named by 1 to 64 letters, digits, dots, dashes or underscores.' => ['schedules' => ['a/b' => ['report' => 'r', 'every' => '1 day']]],
            'The value "fortnight" is not allowed' => ['schedules' => ['s' => ['report' => 'r', 'every' => '1 day', 'period' => 'fortnight']]],
            'The value "radar" is not allowed' => ['dashboards' => ['d' => ['title' => 'D', 'widgets' => [['series' => 's', 'chart' => 'radar']]]]],
            'The child config "title" under' => ['dashboards' => ['d' => []]],
        ] as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString(trim($message), $invalidConfigurationException->getMessage());
            }
        }

        self::assertTrue($this->load(['mail' => ['enabled' => false]])->hasDefinition(Periods::class));
        self::assertSame([100, 100], $this->load(['chart' => ['width' => 100, 'height' => 100]])->getDefinition(ChartDrawing::class)->getArguments());
        self::assertSame([4000, 4000], $this->load(['chart' => ['width' => 4000, 'height' => 4000]])->getDefinition(ChartDrawing::class)->getArguments());
        self::assertSame(1, $this->load(['max_rows' => 1])->getDefinition(Reports::class)->getArgument(2));
        self::assertSame(10_000_000, $this->load(['max_rows' => 10_000_000])->getDefinition(Reports::class)->getArgument(2));
        self::assertTrue($this->load(['store' => 'directory'])->hasDefinition(DirectoryJobStore::class));
        self::assertSame('Europe/Kiev', $this->load(['timezone' => 'Europe/Kiev'])->getDefinition(Periods::class)->getArgument(1));
        $name = 'd-1.x_'.str_repeat('a', 58);
        self::assertEquals([$name => ['title' => 'D', 'role' => null, 'period' => 'last_30_days', 'periods' => ['last_30_days'], 'widgets' => []]], $this->load(['dashboards' => [$name => ['title' => 'D']]])->getDefinition(Dashboards::class)->getArgument(0));
    }

    public function testTheStylesheetIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkStub());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [\dirname(__DIR__, 2).'/assets/styles' => 'phil-reporting']]]], $container->getExtensionConfig('framework'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));
    }

    public function testTheKeysOfTheTaggedServices(): void
    {
        $container = new ContainerBuilder();
        new PhilReportingBundle()->build($container);
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KeyedServicesPass));

        $this->extension()->load([['store' => 'memory']], $container);
        $container->register('clock', MockClock::class)->setArguments(['2026-11-08 10:00:00 UTC']);
        $container->register(MessageBusInterface::class, NullBus::class);
        $container->register(OrdersSource::class)->setAutoconfigured(true);
        $container->register('visits', VisitsSource::class)->setAutoconfigured(true);
        $container->register('renamed', OrdersSource::class)->addTag('phil_reporting.metric_source', ['key' => 'orders_too']);
        $container->setParameter('report.class', SalesReport::class);
        $container->register('report', '%report.class%')->setAutoconfigured(true);
        $container->register(RecordingDelivery::class)->setAutoconfigured(true);
        $container->compile(true);

        $sources = $container->get(Sources::class);
        self::assertInstanceOf(Sources::class, $sources);
        self::assertSame(['orders', 'orders_too'], $sources->metricKeys());
        self::assertSame(['visits'], $sources->seriesKeys());
        self::assertInstanceOf(VisitsSource::class, $sources->series('visits'));
        $reports = $container->get(Reports::class);
        self::assertInstanceOf(Reports::class, $reports);
        self::assertSame(['sales'], $reports->keys());
        self::assertInstanceOf(JsonFormat::class, $reports->format('json'));
        self::assertInstanceOf(TsvFormat::class, $reports->format('tsv'));
    }

    public function testATaggedServiceOfTheWrongKind(): void
    {
        $container = new ContainerBuilder();
        $container->register('wrong', VisitsSource::class)->addTag('phil_reporting.metric_source');

        $this->expectExceptionObject(new InvalidArgumentException(\sprintf('The service "wrong" is tagged "phil_reporting.metric_source" but does not implement %s.', MetricSource::class)));

        new KeyedServicesPass()->process($container);
    }

    public function testAServiceNamedByItsClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(OrdersSource::class, new Definition())->addTag('phil_reporting.metric_source')->addTag('phil_reporting.metric_source', ['key' => 'second']);

        new KeyedServicesPass()->process($container);

        self::assertSame([['key' => 'orders'], ['key' => 'second']], $container->getDefinition(OrdersSource::class)->getTag('phil_reporting.metric_source'));
    }

    private function locator(string $tag): ServiceLocatorArgument
    {
        return new ServiceLocatorArgument(new TaggedIteratorArgument($tag, 'key', true));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilReportingBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
