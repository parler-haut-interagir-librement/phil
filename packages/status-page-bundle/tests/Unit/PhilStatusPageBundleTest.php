<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit;

use LogicException;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Phil\StatusPageBundle\Application\RecordsReader;
use Phil\StatusPageBundle\Application\Render\AtomFeed;
use Phil\StatusPageBundle\Application\Render\JsonDocument;
use Phil\StatusPageBundle\Application\SnapshotPush;
use Phil\StatusPageBundle\Application\StatusPageBuilder;
use Phil\StatusPageBundle\Domain\ValueObject\Component;
use Phil\StatusPageBundle\Infrastructure\Console\PushCommand;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\StatusPageServices;
use Phil\StatusPageBundle\Infrastructure\Http\StatusPageController;
use Phil\StatusPageBundle\Infrastructure\Messenger\PushStatusSnapshotHandler;
use Phil\StatusPageBundle\Infrastructure\Publisher\DirectoryPublisher;
use Phil\StatusPageBundle\Infrastructure\Publisher\FlysystemPublisher;
use Phil\StatusPageBundle\Infrastructure\Records\ConfiguredRecords;
use Phil\StatusPageBundle\Infrastructure\Records\JsonFileRecords;
use Phil\StatusPageBundle\Infrastructure\Scheduler\StatusPageSchedule;
use Phil\StatusPageBundle\Infrastructure\Source\DeclaredStatuses;
use Phil\StatusPageBundle\Infrastructure\Source\MonitoringStatuses;
use Phil\StatusPageBundle\Infrastructure\Twig\KitComponents;
use Phil\StatusPageBundle\Infrastructure\Twig\StatusPageExtension;
use Phil\StatusPageBundle\Infrastructure\Twig\TwigPageRenderer;
use Phil\StatusPageBundle\PhilStatusPageBundle;
use Phil\StatusPageBundle\Tests\Unit\Support\FakeMonitoring;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilStatusPageBundle::class)]
#[CoversClass(StatusPageServices::class)]
#[CoversClass(MonitoringPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilStatusPageBundleTest extends TestCase
{
    public function testTheDefaultsServeAPageOfDeclaredStatesFromTheConfiguration(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_status_page', $this->extension()->getAlias());
        $builder = $container->getDefinition(StatusPageBuilder::class);
        self::assertTrue($builder->isPublic());
        self::assertSame('Service status', $builder->getArgument(0));
        self::assertSame([], $builder->getArgument(1));
        self::assertEquals(new Reference(ComponentStatuses::class), $builder->getArgument(2));
        self::assertEquals(new Reference(StatusRecords::class), $builder->getArgument(3));
        self::assertEquals(new Reference('clock'), $builder->getArgument(4));
        self::assertSame(90, $builder->getArgument(5));

        self::assertSame(DeclaredStatuses::class, (string) $container->getAlias(ComponentStatuses::class));
        self::assertTrue($container->getAlias(ComponentStatuses::class)->isPublic());
        self::assertSame(ConfiguredRecords::class, (string) $container->getAlias(StatusRecords::class));
        self::assertTrue($container->getAlias(StatusRecords::class)->isPublic());
        self::assertSame(['incidents' => [], 'maintenances' => []], $container->getDefinition(ConfiguredRecords::class)->getArgument(0));
        self::assertSame(TwigPageRenderer::class, (string) $container->getAlias(PageRenderer::class));
        self::assertSame([TwigPageRenderer::LAYOUT, null], \array_slice($container->getDefinition(TwigPageRenderer::class)->getArguments(), 1));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertTrue($container->getDefinition(StatusPageExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(StatusPageExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([new Reference(KitComponents::class)], $container->getDefinition(StatusPageExtension::class)->getArguments());
        foreach ([RecordsReader::class, JsonDocument::class, AtomFeed::class] as $id) {
            self::assertTrue($container->hasDefinition($id), $id.' is a service.');
        }

        self::assertSame(60, $container->getDefinition(MonitoringStatuses::class)->getArgument(5));

        $controller = $container->getDefinition(StatusPageController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals(new Reference('router'), $controller->getArgument(4));
        self::assertSame(60, $controller->getArgument(5));

        foreach ([SnapshotPush::class, PushCommand::class, PushStatusSnapshotHandler::class, StatusPageSchedule::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id.' needs a target.');
        }

        // phil/monitoring-bundle is installed with the tests: "auto" prepares its source, which the pass keeps or drops.
        self::assertTrue($container->hasDefinition(MonitoringStatuses::class));
        self::assertSame('auto', $container->getParameter(StatusPageServices::SOURCE_PARAMETER));
    }

    public function testComponentsGiveTheirDeclaredStatesChecksAndTasks(): void
    {
        $container = $this->load([[
            'title' => 'Example',
            'history_days' => 30,
            'cache_max_age' => 0,
            'clock' => 'app.clock',
            'layout' => 'base.html.twig',
            'kit_subdirectory' => 'kit',
            'monitoring' => ['cache_ttl' => 15, 'cache_pool' => 'cache.status'],
            'components' => [
                'web-site' => ['name' => 'Website', 'description' => 'Pages', 'checks' => ['database']],
                'api' => ['name' => 'API', 'group' => 'Platform', 'status' => 'degraded', 'tasks' => ['backup']],
            ],
        ]]);

        $builder = $container->getDefinition(StatusPageBuilder::class);
        self::assertSame('Example', $builder->getArgument(0));
        self::assertEquals([
            new Definition(Component::class, ['web-site', 'Website', 'Pages', null]),
            new Definition(Component::class, ['api', 'API', null, 'Platform']),
        ], $builder->getArgument(1));
        self::assertEquals(new Reference('app.clock'), $builder->getArgument(4));
        self::assertSame(30, $builder->getArgument(5));
        self::assertSame(['web-site' => 'operational', 'api' => 'degraded'], $container->getDefinition(DeclaredStatuses::class)->getArgument(0));
        self::assertSame([DeclaredStatuses::class, 'fromValues'], $container->getDefinition(DeclaredStatuses::class)->getFactory());

        $monitoring = $container->getDefinition(MonitoringStatuses::class);
        self::assertEquals(new Reference(MonitoringStatus::class), $monitoring->getArgument(0));
        self::assertEquals(new Reference(DeclaredStatuses::class), $monitoring->getArgument(1));
        self::assertSame(['web-site' => ['database'], 'api' => []], $monitoring->getArgument(2));
        self::assertSame(['web-site' => [], 'api' => ['backup']], $monitoring->getArgument(3));
        self::assertEquals(new Reference('cache.status', ContainerInterface::NULL_ON_INVALID_REFERENCE), $monitoring->getArgument(4));
        self::assertSame(15, $monitoring->getArgument(5));

        self::assertSame(['base.html.twig', null], \array_slice($container->getDefinition(TwigPageRenderer::class)->getArguments(), 1));
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame(0, $container->getDefinition(StatusPageController::class)->getArgument(5));
    }

    public function testTheDeclaredSourceLeavesTheMonitoringOut(): void
    {
        $container = $this->load([['source' => 'declared']]);

        self::assertFalse($container->hasDefinition(MonitoringStatuses::class));
        self::assertSame(DeclaredStatuses::class, (string) $container->getAlias(ComponentStatuses::class));
    }

    public function testASourceAndRecordsOfTheApplicationReplaceTheBundles(): void
    {
        $container = $this->load([['source_service' => 'app.statuses', 'records' => ['service' => 'app.records', 'file' => '/x.json']]]);

        self::assertSame('app.statuses', (string) $container->getAlias(ComponentStatuses::class));
        self::assertTrue($container->getAlias(ComponentStatuses::class)->isPublic());
        self::assertFalse($container->hasDefinition(MonitoringStatuses::class));
        self::assertSame('app.records', (string) $container->getAlias(StatusRecords::class));
        self::assertTrue($container->getAlias(StatusRecords::class)->isPublic());
        self::assertFalse($container->hasDefinition(JsonFileRecords::class));
        self::assertFalse($container->hasDefinition(ConfiguredRecords::class));
    }

    public function testARecordsFileIsReadOnEachPage(): void
    {
        $container = $this->load([['records' => ['file' => '/var/status.json', 'incidents' => 'ignored']]]);

        self::assertSame(JsonFileRecords::class, (string) $container->getAlias(StatusRecords::class));
        self::assertTrue($container->getAlias(StatusRecords::class)->isPublic());
        self::assertSame('/var/status.json', $container->getDefinition(JsonFileRecords::class)->getArgument(0));
        self::assertFalse($container->hasDefinition(ConfiguredRecords::class));
    }

    public function testInvalidRecordsInTheConfigurationAreRefusedAtOnce(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_status_page.records: The incidents of incidents are a list.');

        $this->load([['records' => ['incidents' => 'none']]]);
    }

    public function testTargetsWireThePushTheCommandTheHandlerAndTheSchedule(): void
    {
        $container = $this->load([['snapshot' => [
            'base_url' => 'https://status.example.org/',
            'stylesheet' => 'https://cdn.example.org/status.css',
            'schedule' => '5 minutes',
            'targets' => [
                'local-copy' => ['type' => 'directory', 'path' => '/srv/status'],
                'bucket' => ['type' => 'flysystem', 'storage' => 'status.storage', 'prefix' => 'public', 'visibility' => 'public'],
                'own' => ['type' => 'service', 'service' => 'app.publisher'],
            ],
        ]]]);

        self::assertSame(DirectoryPublisher::class, $container->getDefinition('phil_status_page.publisher.local-copy')->getClass());
        self::assertSame(['/srv/status'], $container->getDefinition('phil_status_page.publisher.local-copy')->getArguments());
        self::assertSame(FlysystemPublisher::class, $container->getDefinition('phil_status_page.publisher.bucket')->getClass());
        self::assertEquals([new Reference('status.storage'), 'public', 'public'], $container->getDefinition('phil_status_page.publisher.bucket')->getArguments());
        self::assertSame('app.publisher', (string) $container->getAlias('phil_status_page.publisher.own'));

        $push = $container->getDefinition(SnapshotPush::class);
        self::assertTrue($push->isPublic());
        self::assertEquals([
            'local-copy' => new Reference('phil_status_page.publisher.local-copy'),
            'bucket' => new Reference('phil_status_page.publisher.bucket'),
            'own' => new Reference('phil_status_page.publisher.own'),
        ], $push->getArgument(5));
        $url = $push->getArgument(4);
        self::assertInstanceOf(Definition::class, $url);
        self::assertSame(['https://status.example.org/'], $url->getArguments());
        self::assertSame([Url::class, 'fromString'], $url->getFactory());
        self::assertEquals(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $push->getArgument(6));
        self::assertSame('https://cdn.example.org/status.css', $container->getDefinition(TwigPageRenderer::class)->getArgument(2));

        self::assertTrue($container->getDefinition(PushCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(SnapshotPush::class)], $container->getDefinition(PushCommand::class)->getArguments());
        self::assertEquals([new Reference(SnapshotPush::class)], $container->getDefinition(PushStatusSnapshotHandler::class)->getArguments());
        self::assertSame([['handles' => PushStatusSnapshot::class]], $container->getDefinition(PushStatusSnapshotHandler::class)->getTag('messenger.message_handler'));
        self::assertSame(['5 minutes'], $container->getDefinition(StatusPageSchedule::class)->getArguments());
        self::assertSame([['name' => 'phil_status_page']], $container->getDefinition(StatusPageSchedule::class)->getTag('scheduler.schedule_provider'));
    }

    public function testATargetWithoutScheduleHasNoSchedule(): void
    {
        $container = $this->load([['snapshot' => ['base_url' => 'https://status.example.org/', 'targets' => ['a' => ['type' => 'directory', 'path' => '/a']]]]]);

        self::assertTrue($container->hasDefinition(SnapshotPush::class));
        self::assertFalse($container->hasDefinition(StatusPageSchedule::class));
    }

    public function testABaseAddressWithoutTargetPushesNothing(): void
    {
        $container = $this->load([['snapshot' => ['base_url' => 'https://status.example.org/']]]);

        self::assertFalse($container->hasDefinition(SnapshotPush::class));
    }

    public function testTheBoundsOfTheNumbersAreKept(): void
    {
        $container = $this->load([['history_days' => 0, 'cache_max_age' => 0, 'monitoring' => ['cache_ttl' => 0]]]);

        self::assertSame(0, $container->getDefinition(StatusPageBuilder::class)->getArgument(5));
        self::assertSame(0, $container->getDefinition(StatusPageController::class)->getArgument(5));
        self::assertSame(0, $container->getDefinition(MonitoringStatuses::class)->getArgument(5));
    }

    public function testTheAutoSourceAndTheNullVisibilityAreAccepted(): void
    {
        $container = $this->load([['source' => 'auto', 'snapshot' => ['base_url' => 'https://status.example.org/', 'targets' => ['bucket' => ['type' => 'flysystem', 'storage' => 'status.storage', 'visibility' => null]]]]]);

        self::assertSame('auto', $container->getParameter(StatusPageServices::SOURCE_PARAMETER));
        self::assertEquals([new Reference('status.storage'), '', null], $container->getDefinition('phil_status_page.publisher.bucket')->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        $target = ['type' => 'directory', 'path' => '/a'];

        yield 'targets without base address' => [['snapshot' => ['targets' => ['a' => $target]]], 'A snapshot names the address it is read at: set snapshot.base_url.'];
        yield 'schedule without targets' => [['snapshot' => ['schedule' => '1 hour']], 'A snapshot schedule pushes to targets'];
        yield 'base address on localhost' => [['snapshot' => ['base_url' => 'http://localhost/']], 'The base address of the snapshot is an absolute http or https address on a public host.'];
        yield 'relative base address' => [['snapshot' => ['base_url' => '/status/']], 'The base address of the snapshot is an absolute'];
        yield 'base address not a string' => [['snapshot' => ['base_url' => 12]], 'The base address of the snapshot is an absolute'];
        yield 'stylesheet relative' => [['snapshot' => ['stylesheet' => 'style.css']], 'The stylesheet of the snapshot is an absolute http or https address on a public host.'];
        yield 'directory without path' => [['snapshot' => ['base_url' => 'https://s.example.org/', 'targets' => ['a' => ['type' => 'directory']]]], 'A "directory" target names its "path"'];
        yield 'flysystem without storage' => [['snapshot' => ['base_url' => 'https://s.example.org/', 'targets' => ['a' => ['type' => 'flysystem', 'path' => '/a']]]], 'A "directory" target names its "path"'];
        yield 'service without service' => [['snapshot' => ['base_url' => 'https://s.example.org/', 'targets' => ['a' => ['type' => 'service', 'storage' => 'x']]]], 'A "directory" target names its "path"'];
        yield 'unknown status' => [['components' => ['a' => ['name' => 'A', 'status' => 'broken']]], 'The value "broken" is not allowed'];
        yield 'negative history' => [['history_days' => -1], 'The value -1 is too small for path "phil_status_page.history_days"'];
        yield 'negative cache age' => [['cache_max_age' => -1], 'The value -1 is too small for path "phil_status_page.cache_max_age"'];
        yield 'negative cache ttl' => [['monitoring' => ['cache_ttl' => -1]], 'The value -1 is too small for path "phil_status_page.monitoring.cache_ttl"'];
        yield 'history too long' => [['history_days' => 367], 'Should be less than or equal to 366'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testTheConfigurationRefuses(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    public function testThePassMakesTheMonitoringTheSourceWhenItsServiceExists(): void
    {
        $container = $this->load([]);
        $container->register(MonitoringStatus::class, FakeMonitoring::class);
        new MonitoringPass()->process($container);

        self::assertSame(MonitoringStatuses::class, (string) $container->getAlias(ComponentStatuses::class));
        self::assertTrue($container->getAlias(ComponentStatuses::class)->isPublic());
        self::assertTrue($container->hasDefinition(MonitoringStatuses::class));

        $asked = $this->load([['source' => 'monitoring']]);
        $asked->register(MonitoringStatus::class, FakeMonitoring::class);
        new MonitoringPass()->process($asked);
        self::assertSame(MonitoringStatuses::class, (string) $asked->getAlias(ComponentStatuses::class));
    }

    public function testThePassHasNothingToDoWithoutTheMonitoringDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(StatusPageServices::SOURCE_PARAMETER, 'monitoring');
        new MonitoringPass()->process($container);

        self::assertFalse($container->hasAlias(ComponentStatuses::class));
    }

    public function testThePassDropsTheMonitoringWithoutItsServiceUnlessItWasAskedFor(): void
    {
        $container = $this->load([]);
        new MonitoringPass()->process($container);

        self::assertFalse($container->hasDefinition(MonitoringStatuses::class));
        self::assertSame(DeclaredStatuses::class, (string) $container->getAlias(ComponentStatuses::class));

        $asked = $this->load([['source' => 'monitoring']]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_status_page.source "monitoring" reads phil/monitoring-bundle: register PhilMonitoringBundle, or choose "declared".');
        new MonitoringPass()->process($asked);
    }

    public function testThePassDoesNothingWithoutTheMonitoringSource(): void
    {
        $container = $this->load([['source' => 'declared']]);
        new MonitoringPass()->process($container);

        self::assertSame(DeclaredStatuses::class, (string) $container->getAlias(ComponentStatuses::class));
    }

    public function testTheKitIsNamedAfterTheToolkitUnlessTheConfigurationSaysIt(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $plain = $this->load([]);
        new KitSubdirectoryPass()->process($plain);
        self::assertSame('', $plain->getDefinition(KitComponents::class)->getArgument(0));

        $nullToolkit = $this->load([]);
        $nullToolkit->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($nullToolkit);
        self::assertSame('', $nullToolkit->getDefinition(KitComponents::class)->getArgument(0));

        $own = $this->load([['kit_subdirectory' => 'kit']]);
        $own->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($own);
        self::assertSame('kit', $own->getDefinition(KitComponents::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilStatusPageBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $passes);
        self::assertContains(MonitoringPass::class, $passes);
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilStatusPageBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
