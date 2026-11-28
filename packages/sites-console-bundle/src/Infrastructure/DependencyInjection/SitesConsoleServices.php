<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\DependencyInjection;

use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Console\Fleet;
use Phil\SitesConsoleBundle\Application\Console\Journal;
use Phil\SitesConsoleBundle\Application\Console\KeyRotations;
use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore;
use Phil\SitesConsoleBundle\Application\Contract\InstalledPackages;
use Phil\SitesConsoleBundle\Application\Contract\NonceStore;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheConsoleKeyStore;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheNonceStore;
use Phil\SitesConsoleBundle\Infrastructure\Composer\LockFilePackages;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysRotateCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PullCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PurgeCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PushCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\SiteAddCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\SiteRemoveCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\UpdatePlanCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\UpdateRunCommand;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineUpdatePlanRepository;
use Phil\SitesConsoleBundle\Infrastructure\Http\AgentController;
use Phil\SitesConsoleBundle\Infrastructure\Http\ConsoleController;
use Phil\SitesConsoleBundle\Infrastructure\Http\HttpAgentClient;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryKeyRotationRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryUpdatePlanRepository;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\DeliverConfigurationPushHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\PullSiteHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\RunUpdatePlanHandler;
use Phil\SitesConsoleBundle\Infrastructure\Twig\KitComponents;
use Phil\SitesConsoleBundle\Infrastructure\Twig\SitesConsoleExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The services of the bundle, from its processed configuration: the console's when it is on, the agent's when it is
 * on, both in one application when both are.
 *
 * @phpstan-type ConsoleConfig array{
 *     enabled: bool,
 *     private_key: ?string,
 *     next_private_key: ?string,
 *     store: 'doctrine'|'memory',
 *     entity_manager: string,
 *     http_client: string,
 *     allow_insecure_urls: bool,
 *     timeout: float,
 *     update_timeout: float,
 *     watched_packages: list<string>,
 *     expected_versions: array<string, string>,
 *     snapshot_retention_days: int,
 *     roles: array{viewer: string, operator: string},
 *     layout: string,
 *     kit_subdirectory: ?string,
 * }
 * @phpstan-type AgentConfig array{
 *     enabled: bool,
 *     site_id: ?string,
 *     private_key: ?string,
 *     console_public_key: ?string,
 *     console_next_public_key: ?string,
 *     lock_file: string,
 *     include_dev: bool,
 *     packages: list<string>,
 *     configuration_keys: list<string>,
 *     configuration_applier: ?string,
 *     update_runner: ?string,
 *     health: 'auto'|'monitoring'|'none',
 *     cache_pool: string,
 *     key_pool: ?string,
 *     lock_factory: string,
 *     max_clock_skew: int,
 * }
 * @phpstan-type Config array{clock: string, console: ConsoleConfig, agent: AgentConfig}
 */
final readonly class SitesConsoleServices
{
    /** The health the agent's configuration asks for, read by MonitoringPass. */
    public const string HEALTH_PARAMETER = '.phil_sites_console.agent.health';

    public const string STORE_DOCTRINE = 'doctrine';

    public const string STORE_MEMORY = 'memory';

    /** How long an agent remembers a push or an update step it has answered. */
    public const int IDEMPOTENCY_RETENTION = 604_800;

    /** How long an agent holds a push or an update step it is running. */
    public const int IDEMPOTENCY_RESERVATION = 900;

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(KeysCommand::class, KeysCommand::class)->setAutoconfigured(true);

        if ($config['console']['enabled']) {
            self::console($container, $config['console'], $config['clock']);
        }

        if ($config['agent']['enabled']) {
            self::agent($container, $config['agent'], $config['clock']);
        }
    }

    /**
     * @param ConsoleConfig $config
     */
    private static function console(ContainerBuilder $container, array $config, string $clock): void
    {
        $clockReference = new Reference($clock);
        self::stores($container, $config);

        $container->register('phil_sites_console.console_key', SigningKey::class)
            ->setFactory([SigningKey::class, 'fromBase64'])
            ->setArguments([$config['private_key']]);

        if (null !== $config['next_private_key']) {
            $container->register('phil_sites_console.console_next_key', SigningKey::class)
                ->setFactory([SigningKey::class, 'fromBase64'])
                ->setArguments([$config['next_private_key']]);
        }

        $container->register(ConsoleSigning::class, ConsoleSigning::class)
            ->setArguments([
                new Reference('phil_sites_console.console_key'),
                null === $config['next_private_key'] ? null : new Reference('phil_sites_console.console_next_key'),
                new Reference(KeyRotationRepository::class),
            ]);
        $container->register(HttpAgentClient::class, HttpAgentClient::class)
            ->setArguments([new Reference($config['http_client']), new Reference(ConsoleSigning::class), $clockReference, $config['timeout'], $config['update_timeout']]);
        $container->setAlias(AgentClient::class, HttpAgentClient::class);

        $container->register(AlertRules::class, AlertRules::class)->setArguments([$config['watched_packages'], $config['expected_versions']]);
        $container->register(Journal::class, Journal::class)->setArguments([new Reference(JournalRepository::class), $clockReference]);
        $container->register(SiteRegistry::class, SiteRegistry::class)
            ->setArguments([new Reference(SiteRepository::class), new Reference(SnapshotRepository::class), new Reference(Journal::class), $clockReference, $config['allow_insecure_urls']])
            ->setPublic(true);
        $container->register(Fleet::class, Fleet::class)
            ->setArguments([new Reference(SiteRegistry::class), new Reference(SnapshotRepository::class), new Reference(AlertRules::class)])
            ->setPublic(true);
        $container->register(Puller::class, Puller::class)
            ->setArguments([
                new Reference(SiteRegistry::class),
                new Reference(AgentClient::class),
                new Reference(SnapshotRepository::class),
                new Reference(AlertRules::class),
                $clockReference,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        foreach ([ConfigurationPushes::class => ConfigurationPushRepository::class, UpdatePlans::class => UpdatePlanRepository::class] as $service => $repository) {
            $container->register($service, $service)
                ->setArguments([new Reference(SiteRegistry::class), new Reference($repository), new Reference(AgentClient::class), new Reference(Journal::class), $clockReference])
                ->setPublic(true);
        }

        foreach ([SiteAddCommand::class => [SiteRegistry::class], SiteRemoveCommand::class => [SiteRegistry::class], PullCommand::class => [SiteRegistry::class, Puller::class], PushCommand::class => [ConfigurationPushes::class], UpdatePlanCommand::class => [SiteRegistry::class, UpdatePlans::class], UpdateRunCommand::class => [UpdatePlans::class]] as $command => $arguments) {
            $container->register($command, $command)
                ->setArguments(array_map(static fn (string $id): Reference => new Reference($id), $arguments))
                ->setAutoconfigured(true);
        }

        $container->register(KeyRotations::class, KeyRotations::class)
            ->setArguments([new Reference(SiteRegistry::class), new Reference(KeyRotationRepository::class), new Reference(AgentClient::class), new Reference(ConsoleSigning::class), new Reference(Journal::class), $clockReference])
            ->setPublic(true);
        $container->register(KeysRotateCommand::class, KeysRotateCommand::class)
            ->setArguments([new Reference(KeyRotations::class), new Reference(ConsoleSigning::class)])
            ->setAutoconfigured(true);

        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([new Reference(SnapshotRepository::class), $clockReference, $config['snapshot_retention_days']])
            ->setAutoconfigured(true);

        if (interface_exists(MessageBusInterface::class)) {
            foreach ([PullSiteHandler::class => Puller::class, DeliverConfigurationPushHandler::class => ConfigurationPushes::class, RunUpdatePlanHandler::class => UpdatePlans::class] as $handler => $service) {
                $container->register($handler, $handler)->setArguments([new Reference($service)])->addTag('messenger.message_handler');
            }
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(SitesConsoleExtension::class, SitesConsoleExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $container->register(ConsoleController::class, ConsoleController::class)
            ->setArguments([
                new Reference(Fleet::class),
                new Reference(Puller::class),
                new Reference(ConfigurationPushes::class),
                new Reference(UpdatePlans::class),
                new Reference(Journal::class),
                new Reference('twig'),
                new Reference('security.authorization_checker'),
                new Reference('security.csrf.token_manager'),
                new Reference('router'),
                $config['roles']['viewer'],
                $config['roles']['operator'],
                $config['layout'],
                new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * @param ConsoleConfig $config
     */
    private static function stores(ContainerBuilder $container, array $config): void
    {
        $stores = self::STORE_MEMORY === $config['store']
            ? [SiteRepository::class => InMemorySiteRepository::class, SnapshotRepository::class => InMemorySnapshotRepository::class, ConfigurationPushRepository::class => InMemoryConfigurationPushRepository::class, UpdatePlanRepository::class => InMemoryUpdatePlanRepository::class, JournalRepository::class => InMemoryJournalRepository::class, KeyRotationRepository::class => InMemoryKeyRotationRepository::class]
            : [SiteRepository::class => DoctrineSiteRepository::class, SnapshotRepository::class => DoctrineSnapshotRepository::class, ConfigurationPushRepository::class => DoctrineConfigurationPushRepository::class, UpdatePlanRepository::class => DoctrineUpdatePlanRepository::class, JournalRepository::class => DoctrineJournalRepository::class, KeyRotationRepository::class => DoctrineKeyRotationRepository::class];

        foreach ($stores as $contract => $class) {
            $definition = $container->register($class, $class);

            if (self::STORE_DOCTRINE === $config['store']) {
                $definition->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))]);
            }

            $container->setAlias($contract, $class)->setPublic(true);
        }
    }

    /**
     * @param AgentConfig $config
     */
    private static function agent(ContainerBuilder $container, array $config, string $clock): void
    {
        $clockReference = new Reference($clock);
        $container->setParameter(self::HEALTH_PARAMETER, $config['health']);

        $container->register('phil_sites_console.agent_key', SigningKey::class)
            ->setFactory([SigningKey::class, 'fromBase64'])
            ->setArguments([$config['private_key']]);
        $container->register('phil_sites_console.console_public_key', VerifyingKey::class)
            ->setFactory([VerifyingKey::class, 'fromBase64'])
            ->setArguments([$config['console_public_key']]);

        if (null !== $config['console_next_public_key']) {
            $container->register('phil_sites_console.console_next_public_key', VerifyingKey::class)
                ->setFactory([VerifyingKey::class, 'fromBase64'])
                ->setArguments([$config['console_next_public_key']]);
        }

        $container->register(CacheConsoleKeyStore::class, CacheConsoleKeyStore::class)->setArguments([new Reference($config['key_pool'] ?? $config['cache_pool'])]);
        $container->setAlias(ConsoleKeyStore::class, CacheConsoleKeyStore::class);
        $container->register(ConsoleKeyRing::class, ConsoleKeyRing::class)
            ->setArguments([
                new Reference('phil_sites_console.console_public_key'),
                null === $config['console_next_public_key'] ? null : new Reference('phil_sites_console.console_next_public_key'),
                new Reference(ConsoleKeyStore::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);

        $container->register(LockFilePackages::class, LockFilePackages::class)->setArguments([$config['lock_file'], $config['include_dev']]);
        $container->setAlias(InstalledPackages::class, LockFilePackages::class);
        $container->register(CacheNonceStore::class, CacheNonceStore::class)->setArguments([new Reference($config['cache_pool']), new Reference($config['lock_factory'])]);
        $container->setAlias(NonceStore::class, CacheNonceStore::class);

        $container->register('phil_sites_console.idempotency_store', CacheIdempotencyStore::class)
            ->setArguments([new Reference($config['cache_pool']), new Reference($config['lock_factory']), $clockReference]);
        $container->register('phil_sites_console.idempotency', IdempotencyGuard::class)
            ->setArguments([new Reference('phil_sites_console.idempotency_store'), $clockReference, self::IDEMPOTENCY_RETENTION, self::IDEMPOTENCY_RESERVATION]);

        $container->register(RequestVerifier::class, RequestVerifier::class)
            ->setArguments([$config['site_id'], new Reference(ConsoleKeyRing::class), new Reference(NonceStore::class), $clockReference, $config['max_clock_skew']]);
        $container->register(AgentEndpoints::class, AgentEndpoints::class)
            ->setArguments([
                $config['site_id'],
                new Reference(InstalledPackages::class),
                new Reference(SiteHealth::class),
                new Reference('phil_sites_console.idempotency'),
                $clockReference,
                $config['packages'],
                $config['configuration_keys'],
                null === $config['configuration_applier'] ? null : new Reference($config['configuration_applier']),
                null === $config['update_runner'] ? null : new Reference($config['update_runner']),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ConsoleKeyRing::class),
            ])
            ->setPublic(true);
        $container->register(AgentController::class, AgentController::class)
            ->setArguments([
                new Reference(RequestVerifier::class),
                new Reference(AgentEndpoints::class),
                new Reference('phil_sites_console.agent_key'),
                $config['site_id'],
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }
}
