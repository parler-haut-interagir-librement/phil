<?php

declare(strict_types=1);

namespace Phil\AuditBundle;

use LogicException;
use Override;
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
use Phil\AuditBundle\Domain\Read\AuditQuery;
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
use Phil\TenancyBundle\Application\TenantContext;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * An append-only, hash-chained audit trail that stays compatible with erasure: rows chained per segment in a table
 * of a Doctrine connection, indexed by their facts and read a page at a time, anchored outside the database, and
 * verified by a command that fails loudly.
 *
 * ```yaml
 * phil_audit:
 *     connection: default                     # null: the application gives its own AuditRowRepository
 *     serializer: App\Audit\FrozenSerializer  # the application's frozen CanonicalSerializer
 *     anchor_writer: App\Audit\ObjectLockAnchors
 *     anchor_reader: App\Audit\ObjectLockAnchors
 *     # or, for development only: anchor_file: '%kernel.project_dir%/var/audit-anchors.jsonl'
 *     dispatch_bus: messenger.bus.default     # each recorded row dispatched as an AuditRecorded message
 *     workflow_segment: workflow              # each transition of an Auditable subject recorded there
 *     site_segments: true                     # each tenant's rows in a chain of its own
 *     feed:                                   # the facts sent to dashboards, identifiers only
 *         json_lines: '%kernel.logs_dir%/audit-facts.jsonl'
 *         # or otlp: { endpoint: 'https://collector.internal:4318/v1/logs', service_name: shop }
 *         # or exporter: App\Audit\MyFactExporter
 * ```
 *
 * @phpstan-type FeedConfig array{name: string, batch: int, exporter: ?string, json_lines: ?string, otlp: array{endpoint: ?string, service_name: string, headers: array<string, string>, http_client: string}, checkpoints: ?string}
 */
final class PhilAuditBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_audit';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info("The Doctrine connection of the trail's table; null, the application gives its own AuditRowRepository and wires the services.")
                    ->defaultValue('default')
                ->end()
                ->scalarNode('serializer')
                    ->info('Service id of the frozen CanonicalSerializer rows are written with; null, no AuditTrail is registered.')
                    ->defaultNull()
                ->end()
                ->scalarNode('anchor_writer')
                    ->info('Service id of the AnchorWriter, to append-only storage; null, no anchoring job is registered.')
                    ->defaultNull()
                ->end()
                ->scalarNode('anchor_reader')
                    ->info('Service id of the AnchorReader verification reads the anchors back with; null, the chain is verified without them.')
                    ->defaultNull()
                ->end()
                ->scalarNode('anchor_file')
                    ->info('A file anchors are appended to, as reader and writer: for development only, unless it lives on WORM storage.')
                    ->defaultNull()
                ->end()
                ->scalarNode('dispatch_bus')
                    ->info('Service id of a Messenger bus each recorded row is dispatched on, as an AuditRecorded message carrying its place only; null, nothing is dispatched.')
                    ->defaultNull()
                ->end()
                ->scalarNode('workflow_segment')
                    ->info('The segment the completed transitions of Auditable workflow subjects are recorded in; null, transitions are not recorded. Needs a serializer.')
                    ->defaultNull()
                ->end()
                ->booleanNode('site_segments')
                    ->info("Whether the rows of each tenant go to a segment of that tenant's own, chained, anchored and verified on its own.")
                    ->defaultFalse()
                ->end()
                ->arrayNode('feed')
                    ->info('The facts of the trail — identifiers, never a payload — sent to dashboards through one exporter.')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $feed): bool => \count(array_filter([$feed['exporter'] ?? null, $feed['json_lines'] ?? null, \is_array($feed['otlp'] ?? null) ? $feed['otlp']['endpoint'] ?? null : null], static fn (mixed $value): bool => null !== $value)) > 1)
                        ->thenInvalid('The feed has one exporter: set exporter, json_lines or otlp.endpoint, not several.')
                    ->end()
                    ->children()
                        ->scalarNode('name')->info("The feed's name, the key of its places.")->defaultValue('dashboards')->cannotBeEmpty()->end()
                        ->integerNode('batch')->info('The rows read and sent at once.')->defaultValue(DashboardFeed::DEFAULT_BATCH)->min(1)->max(AuditQuery::MAX_LIMIT)->end()
                        ->scalarNode('exporter')->info('Service id of a FactExporter of the application.')->defaultNull()->end()
                        ->scalarNode('json_lines')->info('A file — or a php:// stream — the facts are appended to, one JSON line each.')->defaultNull()->end()
                        ->arrayNode('otlp')
                            ->info('An OpenTelemetry collector the facts are sent to as log records, by OTLP over HTTP and JSON.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('endpoint')->info("The collector's logs endpoint, such as https://collector.internal:4318/v1/logs, read by phil/url: a host name of two labels at least, or localhost.")->defaultNull()->end()
                                ->scalarNode('service_name')->info('The service.name of the resource.')->defaultValue('symfony')->cannotBeEmpty()->end()
                                ->arrayNode('headers')->info('Headers sent with every request.')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
                                ->scalarNode('http_client')->info('Service id of the HTTP client.')->defaultValue('http_client')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->scalarNode('checkpoints')->info("Service id of the FeedCheckpoints; null, a table of the trail's connection.")->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ContextPass());
    }

    /**
     * Without DoctrineBundle, the application gives its own repository.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('phil_audit', ['connection' => null]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: ?string, serializer: ?string, anchor_writer: ?string, anchor_reader: ?string, anchor_file: ?string, dispatch_bus: ?string, workflow_segment: ?string, site_segments: bool, feed: FeedConfig} $config */
        $services = $container->services();
        $clock = new Reference('clock');
        $port = new ReflectionClass(DbalAuditRowRepository::class)->getInterfaceNames()[0];

        // Without a connection, the application gives its own AuditRowRepository and wires the services over it.
        if (null === $config['connection']) {
            return;
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $services->set(DbalAuditRowRepository::class)->args([$connection]);
        $services->alias($port, DbalAuditRowRepository::class)->public();
        $services->set(AuditSchemaListener::class)
            ->args([$connection])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);

        $writer = $config['anchor_writer'];
        $reader = $config['anchor_reader'];

        if (null !== $config['anchor_file']) {
            $services->set(JsonLinesAnchorStore::class)->args([$config['anchor_file']]);
            $writer ??= JsonLinesAnchorStore::class;
            $reader ??= JsonLinesAnchorStore::class;
        }

        $this->registerContext($config['dispatch_bus'], $container);

        if (null !== $config['serializer']) {
            $services->set(AuditTrail::class)
                ->args([new Reference($port), new Reference($config['serializer']), $clock, $this->optional(TenantScope::class), $this->optional(AuditNotifier::class), $config['site_segments']])
                ->public();
        }

        $this->registerWorkflow($config['workflow_segment'], null !== $config['serializer'], $container);

        $services->set(TrailVerification::class)
            ->args([new Reference($port), null === $reader ? null : new Reference($reader)])
            ->public();
        $services->set(DbalAuditLogReader::class)->args([$connection]);
        $services->alias(AuditLogReader::class, DbalAuditLogReader::class);
        $services->set(AuditLog::class)->args([new Reference(AuditLogReader::class), $this->optional(TenantScope::class)])->public();
        $services->set(ChainStatus::class)->args([new Reference($port), new Reference(TrailVerification::class), $this->optional(TenantScope::class)])->public();
        $this->registerFeed($config['feed'], $connection, null !== $config['dispatch_bus'], $container);

        if (null !== $writer) {
            $services->set(Anchoring::class)->args([new Reference($port), new Reference($writer)])->public();
        }

        if (class_exists(Command::class)) {
            $services->set(VerifyCommand::class)->args([new Reference(TrailVerification::class)])->tag('console.command');

            if (null !== $writer) {
                $services->set(AnchorCommand::class)->args([new Reference(Anchoring::class), $clock])->tag('console.command');
            }
        }
    }

    /**
     * What the trail reads of its context, each registered when the component or the bundle it reads is installed —
     * `ContextPass` removes it when that bundle is not registered —, and aliased to its port unless the application
     * aliased its own: the Messenger bus rows are dispatched on, the tenant of `phil/tenancy-bundle`, and the account
     * of the Security component.
     *
     * @param string|null $bus the `dispatch_bus` key
     */
    private function registerContext(?string $bus, ContainerConfigurator $container): void
    {
        $services = $container->services();

        if (null !== $bus) {
            $services->set(MessengerAuditNotifier::class)->args([new Reference($bus)]);
            $services->alias(AuditNotifier::class, MessengerAuditNotifier::class);
        }

        if (class_exists(TenantContext::class)) {
            $services->set(TenancyTenantScope::class)->args([new Reference(TenantContext::class)]);
            $services->alias(TenantScope::class, TenancyTenantScope::class);
        }

        if (interface_exists(TokenStorageInterface::class)) {
            $services->set(SecurityActorResolver::class)->args([new Reference('security.token_storage')]);
            $services->alias(ActorResolver::class, SecurityActorResolver::class);
        }
    }

    /**
     * The feed to the dashboards, when an exporter is configured: the exporter, its places, the service, its command,
     * and — when rows are dispatched — the handler that feeds after each row.
     *
     * @param FeedConfig $feed
     */
    private function registerFeed(array $feed, Reference $connection, bool $dispatched, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $exporter = $feed['exporter'];

        if (null !== $feed['json_lines']) {
            $services->set(JsonLinesFactExporter::class)->args([$feed['json_lines']]);
            $exporter = JsonLinesFactExporter::class;
        }

        if (null !== $feed['otlp']['endpoint']) {
            if (!class_exists(Url::class)) {
                throw new LogicException('phil_audit.feed.otlp reads its endpoint with phil/url: composer require phil/url.');
            }

            // Checked as the container compiles, rather than at the first batch.
            try {
                OtlpLogExporter::endpoint($feed['otlp']['endpoint']);
            } catch (InvalidUrl $invalidUrl) {
                throw new InvalidConfigurationException(\sprintf('phil_audit.feed.otlp.endpoint: %s', $invalidUrl->getMessage()), $invalidUrl->getCode(), previous: $invalidUrl);
            }

            $services->set(OtlpLogExporter::class)->args([new Reference($feed['otlp']['http_client']), $feed['otlp']['endpoint'], $feed['otlp']['service_name'], $feed['otlp']['headers']]);
            $exporter = OtlpLogExporter::class;
        }

        if (null === $exporter) {
            return;
        }

        $checkpoints = $feed['checkpoints'];

        if (null === $checkpoints) {
            $services->set(DbalFeedCheckpoints::class)->args([$connection]);
            $checkpoints = DbalFeedCheckpoints::class;
        }

        $services->set(DashboardFeed::class)
            ->args([new Reference(new ReflectionClass(DbalAuditRowRepository::class)->getInterfaceNames()[0]), new Reference($exporter), new Reference($checkpoints), $feed['name'], $feed['batch']])
            ->public();

        if (class_exists(Command::class)) {
            $services->set(FeedCommand::class)->args([new Reference(DashboardFeed::class)])->tag('console.command');
        }

        if ($dispatched) {
            $services->set(FeedOnRecorded::class)->args([new Reference(DashboardFeed::class)])->tag('messenger.message_handler', ['handles' => AuditRecorded::class]);
        }
    }

    /**
     * @param string|null $segment the `workflow_segment` key
     */
    private function registerWorkflow(?string $segment, bool $recording, ContainerConfigurator $container): void
    {
        if (null === $segment) {
            return;
        }

        if (!$recording) {
            throw new LogicException('phil_audit.workflow_segment records transitions through the AuditTrail: set phil_audit.serializer too.');
        }

        $container->services()->set(WorkflowAuditListener::class)
            ->args([new Reference(AuditTrail::class), $segment, $this->optional(ActorResolver::class)])
            ->tag('kernel.event_listener', ['event' => 'workflow.completed']);
    }

    /**
     * A reference to a port the application may leave without an implementation: `null` then.
     */
    private function optional(string $id): Reference
    {
        return new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }
}
