<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit;

use LogicException;
use Phil\AuditBundle\Application\Anchoring;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\AuditNotifier;
use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Application\Message\AuditRecorded;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Infrastructure\Anchor\JsonLinesAnchorStore;
use Phil\AuditBundle\Infrastructure\Console\AnchorCommand;
use Phil\AuditBundle\Infrastructure\Console\FeedCommand;
use Phil\AuditBundle\Infrastructure\Console\VerifyCommand;
use Phil\AuditBundle\Infrastructure\DependencyInjection\ContextPass;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditSchemaListener;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalFeedCheckpoints;
use Phil\AuditBundle\Infrastructure\Export\JsonLinesFactExporter;
use Phil\AuditBundle\Infrastructure\Export\OtlpLogExporter;
use Phil\AuditBundle\Infrastructure\Messenger\FeedOnRecorded;
use Phil\AuditBundle\Infrastructure\Messenger\MessengerAuditNotifier;
use Phil\AuditBundle\Infrastructure\Security\SecurityActorResolver;
use Phil\AuditBundle\Infrastructure\Tenancy\TenancyTenantScope;
use Phil\AuditBundle\Infrastructure\Workflow\WorkflowAuditListener;
use Phil\AuditBundle\PhilAuditBundle;
use Phil\TenancyBundle\Application\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAuditBundle::class)]
#[CoversClass(ContextPass::class)]
final class PhilAuditBundleTest extends TestCase
{
    public function testEveryServiceIsWired(): void
    {
        $container = $this->load(['connection' => 'audit', 'serializer' => 'app.serializer', 'anchor_writer' => 'app.anchors', 'anchor_reader' => 'app.reader']);
        $connection = new Reference('doctrine.dbal.audit_connection');
        $port = new Reference(AuditRowRepository::class);
        $clock = new Reference('clock');

        self::assertSame('phil_audit', $this->extension()->getAlias());
        self::assertEquals([$connection], $container->getDefinition(DbalAuditRowRepository::class)->getArguments());
        self::assertSame(DbalAuditRowRepository::class, (string) $container->getAlias(AuditRowRepository::class));
        self::assertTrue($container->getAlias(AuditRowRepository::class)->isPublic());
        self::assertEquals([$connection], $container->getDefinition(AuditSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'audit']], $container->getDefinition(AuditSchemaListener::class)->getTag('doctrine.event_listener'));
        $optional = static fn (string $id): Reference => new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([$port, new Reference('app.serializer'), $clock, $optional(TenantScope::class), $optional(AuditNotifier::class), false], $container->getDefinition(AuditTrail::class)->getArguments());
        self::assertEquals([$connection], $container->getDefinition(DbalAuditLogReader::class)->getArguments());
        self::assertSame(DbalAuditLogReader::class, (string) $container->getAlias(AuditLogReader::class));
        self::assertEquals([new Reference(AuditLogReader::class), $optional(TenantScope::class)], $container->getDefinition(AuditLog::class)->getArguments());
        self::assertEquals([$port, new Reference(TrailVerification::class), $optional(TenantScope::class)], $container->getDefinition(ChainStatus::class)->getArguments());
        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(TenancyTenantScope::class)->getArguments());
        self::assertSame(TenancyTenantScope::class, (string) $container->getAlias(TenantScope::class));
        self::assertEquals([new Reference('security.token_storage')], $container->getDefinition(SecurityActorResolver::class)->getArguments());
        self::assertSame(SecurityActorResolver::class, (string) $container->getAlias(ActorResolver::class));
        self::assertFalse($container->hasDefinition(MessengerAuditNotifier::class));
        self::assertFalse($container->hasDefinition(WorkflowAuditListener::class));
        self::assertEquals([$port, new Reference('app.reader')], $container->getDefinition(TrailVerification::class)->getArguments());
        self::assertEquals([$port, new Reference('app.anchors')], $container->getDefinition(Anchoring::class)->getArguments());
        self::assertEquals([new Reference(Anchoring::class), $clock], $container->getDefinition(AnchorCommand::class)->getArguments());
        self::assertEquals([new Reference(TrailVerification::class)], $container->getDefinition(VerifyCommand::class)->getArguments());

        foreach ([AuditTrail::class, TrailVerification::class, Anchoring::class, AuditLog::class, ChainStatus::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
        }

        foreach ([AnchorCommand::class, VerifyCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        self::assertFalse($container->hasDefinition(JsonLinesAnchorStore::class));
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalAuditRowRepository::class)->getArguments());
        self::assertFalse($container->hasDefinition(AuditTrail::class));
        self::assertFalse($container->hasDefinition(Anchoring::class));
        self::assertFalse($container->hasDefinition(AnchorCommand::class));
        self::assertEquals([new Reference(AuditRowRepository::class), null], $container->getDefinition(TrailVerification::class)->getArguments());

        $alone = $this->load(['connection' => null, 'serializer' => 'app.serializer', 'anchor_file' => '/var/anchors.jsonl']);
        self::assertSame(['service_container'], array_keys($alone->getDefinitions()));
    }

    public function testAFileOfAnchorsIsReaderAndWriter(): void
    {
        $container = $this->load(['anchor_file' => '/var/anchors.jsonl']);
        $store = new Reference(JsonLinesAnchorStore::class);

        self::assertSame(['/var/anchors.jsonl'], $container->getDefinition(JsonLinesAnchorStore::class)->getArguments());
        self::assertEquals([new Reference(AuditRowRepository::class), $store], $container->getDefinition(Anchoring::class)->getArguments());
        self::assertEquals([new Reference(AuditRowRepository::class), $store], $container->getDefinition(TrailVerification::class)->getArguments());

        $own = $this->load(['anchor_file' => '/var/anchors.jsonl', 'anchor_writer' => 'app.anchors', 'anchor_reader' => 'app.reader']);
        self::assertEquals(new Reference('app.anchors'), $own->getDefinition(Anchoring::class)->getArgument(1));
        self::assertEquals(new Reference('app.reader'), $own->getDefinition(TrailVerification::class)->getArgument(1));
    }

    public function testRowsAreDispatchedAndTransitionsRecordedWhenConfigured(): void
    {
        $container = $this->load(['serializer' => 'app.serializer', 'dispatch_bus' => 'messenger.bus.audit', 'workflow_segment' => 'states']);

        self::assertEquals([new Reference('messenger.bus.audit')], $container->getDefinition(MessengerAuditNotifier::class)->getArguments());
        self::assertSame(MessengerAuditNotifier::class, (string) $container->getAlias(AuditNotifier::class));
        self::assertEquals([new Reference(AuditTrail::class), 'states', new Reference(ActorResolver::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(WorkflowAuditListener::class)->getArguments());
        self::assertSame([['event' => 'workflow.completed']], $container->getDefinition(WorkflowAuditListener::class)->getTag('kernel.event_listener'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_audit.workflow_segment records transitions through the AuditTrail: set phil_audit.serializer too.');
        $this->load(['workflow_segment' => 'states']);
    }

    public function testTheFactsAreFedToOneExporter(): void
    {
        $port = new Reference(AuditRowRepository::class);
        $container = $this->load(['feed' => ['json_lines' => '/var/log/facts.jsonl']]);

        self::assertSame(['/var/log/facts.jsonl'], $container->getDefinition(JsonLinesFactExporter::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalFeedCheckpoints::class)->getArguments());
        self::assertEquals([$port, new Reference(JsonLinesFactExporter::class), new Reference(DbalFeedCheckpoints::class), 'dashboards', 500], $container->getDefinition(DashboardFeed::class)->getArguments());
        self::assertTrue($container->getDefinition(DashboardFeed::class)->isPublic());
        self::assertEquals([new Reference(DashboardFeed::class)], $container->getDefinition(FeedCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(FeedCommand::class)->hasTag('console.command'));
        self::assertFalse($container->hasDefinition(FeedOnRecorded::class), 'No message, no handler.');
        self::assertFalse($container->hasDefinition(OtlpLogExporter::class));

        $otlp = $this->load(['dispatch_bus' => 'messenger.bus.audit', 'feed' => ['name' => 'siem', 'batch' => 100, 'checkpoints' => 'app.places', 'otlp' => ['endpoint' => 'https://collector.internal:4318/v1/logs', 'service_name' => 'shop', 'headers' => ['Authorization' => 'Bearer t'], 'http_client' => 'app.http']]]);
        self::assertEquals([new Reference('app.http'), 'https://collector.internal:4318/v1/logs', 'shop', ['Authorization' => 'Bearer t']], $otlp->getDefinition(OtlpLogExporter::class)->getArguments());
        self::assertEquals([$port, new Reference(OtlpLogExporter::class), new Reference('app.places'), 'siem', 100], $otlp->getDefinition(DashboardFeed::class)->getArguments());
        self::assertFalse($otlp->hasDefinition(DbalFeedCheckpoints::class));
        self::assertEquals([new Reference(DashboardFeed::class)], $otlp->getDefinition(FeedOnRecorded::class)->getArguments());
        self::assertSame([['handles' => AuditRecorded::class]], $otlp->getDefinition(FeedOnRecorded::class)->getTag('messenger.message_handler'));

        $defaults = $this->load(['feed' => ['otlp' => ['endpoint' => 'https://collector.internal/v1/logs']]]);
        self::assertEquals([new Reference('http_client'), 'https://collector.internal/v1/logs', 'symfony', []], $defaults->getDefinition(OtlpLogExporter::class)->getArguments());

        $one = $this->load(['feed' => ['json_lines' => '/var/log/facts.jsonl', 'batch' => 1]]);
        self::assertSame(1, $one->getDefinition(DashboardFeed::class)->getArgument(4));

        $own = $this->load(['feed' => ['exporter' => 'app.exporter']]);
        self::assertEquals(new Reference('app.exporter'), $own->getDefinition(DashboardFeed::class)->getArgument(1));

        $none = $this->load([]);
        self::assertFalse($none->hasDefinition(DashboardFeed::class));
        self::assertFalse($none->hasDefinition(DbalFeedCheckpoints::class));
        self::assertFalse($none->hasDefinition(FeedCommand::class));
    }

    public function testAnEndpointPhilUrlRefusesStopsTheCompilation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_audit.feed.otlp.endpoint: ');

        $this->load(['feed' => ['otlp' => ['endpoint' => 'https://10.0.0.7:4318/v1/logs']]]);
    }

    public function testAFeedHasOneExporter(): void
    {
        foreach ([
            ['exporter' => 'app.exporter', 'json_lines' => '/var/facts.jsonl'],
            ['json_lines' => '/var/facts.jsonl', 'otlp' => ['endpoint' => 'https://collector.internal/v1/logs']],
            ['exporter' => 'app.exporter', 'otlp' => ['endpoint' => 'https://collector.internal/v1/logs']],
            ['batch' => 0],
            ['batch' => 501],
            ['name' => ''],
        ] as $feed) {
            try {
                $this->load(['feed' => $feed]);
                self::fail((string) json_encode($feed));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertNotSame('', $invalidConfigurationException->getMessage());
            }
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The feed has one exporter: set exporter, json_lines or otlp.endpoint, not several.');
        $this->load(['feed' => ['exporter' => 'app.exporter', 'json_lines' => '/var/facts.jsonl']]);
    }

    public function testSiteSegmentsAreTurnedOn(): void
    {
        self::assertTrue($this->load(['serializer' => 'app.serializer', 'site_segments' => true])->getDefinition(AuditTrail::class)->getArgument(5));
    }

    /**
     * The adapters of the context stay only where what they read is registered, and an alias the application gave
     * to another service is kept.
     */
    public function testTheContextAdaptersStayOnlyWhereTheyReadSomething(): void
    {
        $bundle = new PhilAuditBundle();
        $builder = new ContainerBuilder();
        $bundle->build($builder);
        self::assertCount(1, array_filter($builder->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof ContextPass));

        $bare = $this->load([]);
        new ContextPass()->process($bare);
        self::assertFalse($bare->hasDefinition(SecurityActorResolver::class));
        self::assertFalse($bare->hasAlias(ActorResolver::class));
        self::assertFalse($bare->hasDefinition(TenancyTenantScope::class));
        self::assertFalse($bare->hasAlias(TenantScope::class));

        $full = $this->load([]);
        $full->setDefinition('security.token_storage', new Definition());
        $full->setDefinition(TenantContext::class, new Definition(TenantContext::class));
        new ContextPass()->process($full);
        self::assertTrue($full->hasDefinition(SecurityActorResolver::class));
        self::assertSame(SecurityActorResolver::class, (string) $full->getAlias(ActorResolver::class));
        self::assertTrue($full->hasDefinition(TenancyTenantScope::class));
        self::assertSame(TenancyTenantScope::class, (string) $full->getAlias(TenantScope::class));

        $own = $this->load([]);
        $own->setAlias(ActorResolver::class, 'app.actors');
        new ContextPass()->process($own);
        self::assertFalse($own->hasDefinition(SecurityActorResolver::class));
        self::assertSame('app.actors', (string) $own->getAlias(ActorResolver::class));

        $empty = new ContainerBuilder();
        new ContextPass()->process($empty);
        self::assertFalse($empty->hasAlias(ActorResolver::class));
    }

    public function testWithoutDoctrineTheApplicationGivesTheRepository(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertSame([['connection' => null]], $without->getExtensionConfig('phil_audit'));

        $with = new ContainerBuilder();
        $with->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension->prepend($with);
        self::assertSame([], $with->getExtensionConfig('phil_audit'));
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
        $extension = new PhilAuditBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
