<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

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
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
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
use Phil\SitesConsoleBundle\Infrastructure\Messenger\DeliverConfigurationPushHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\PullSiteHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\RunUpdatePlanHandler;
use Phil\SitesConsoleBundle\Infrastructure\Twig\KitComponents;
use Phil\SitesConsoleBundle\Infrastructure\Twig\SitesConsoleExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The services the bundle registers for a console on Doctrine and an agent with every option given: each definition
 * with its class, factory, arguments, visibility and tags, and each alias. A reference to a service reads `@id`, a
 * reference that may be missing `@id?`.
 */
final class ExpectedWiring
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $all = [
            AgentEndpoints::class => [
                'class' => AgentEndpoints::class,
                'factory' => null,
                'arguments' => [
                    'shop',
                    '@Phil\SitesConsoleBundle\Application\Contract\InstalledPackages',
                    '@Phil\SitesConsoleBundle\Application\Contract\SiteHealth',
                    '@phil_sites_console.idempotency',
                    '@clock',
                    [],
                    [],
                    '@app.applier',
                    '@app.runner',
                    '@logger?',
                    '@Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            RequestVerifier::class => [
                'class' => RequestVerifier::class,
                'factory' => null,
                'arguments' => [
                    'shop',
                    '@Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing',
                    '@Phil\SitesConsoleBundle\Application\Contract\NonceStore',
                    '@clock',
                    60,
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            ConfigurationPushes::class => [
                'class' => ConfigurationPushes::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository',
                    '@Phil\SitesConsoleBundle\Application\Contract\AgentClient',
                    '@Phil\SitesConsoleBundle\Application\Console\Journal',
                    '@clock',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            Fleet::class => [
                'class' => Fleet::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository',
                    '@Phil\SitesConsoleBundle\Domain\Alert\AlertRules',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            Journal::class => [
                'class' => Journal::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Domain\Contract\JournalRepository',
                    '@clock',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            Puller::class => [
                'class' => Puller::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Application\Contract\AgentClient',
                    '@Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository',
                    '@Phil\SitesConsoleBundle\Domain\Alert\AlertRules',
                    '@clock',
                    '@event_dispatcher?',
                    '@logger?',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            SiteRegistry::class => [
                'class' => SiteRegistry::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Domain\Contract\SiteRepository',
                    '@Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository',
                    '@Phil\SitesConsoleBundle\Application\Console\Journal',
                    '@clock',
                    true,
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            UpdatePlans::class => [
                'class' => UpdatePlans::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository',
                    '@Phil\SitesConsoleBundle\Application\Contract\AgentClient',
                    '@Phil\SitesConsoleBundle\Application\Console\Journal',
                    '@clock',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            AlertRules::class => [
                'class' => AlertRules::class,
                'factory' => null,
                'arguments' => [
                    [
                        'phil/*',
                        'symfony/http-kernel',
                    ],
                    [],
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            CacheNonceStore::class => [
                'class' => CacheNonceStore::class,
                'factory' => null,
                'arguments' => [
                    '@app.pool',
                    '@app.locks',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            LockFilePackages::class => [
                'class' => LockFilePackages::class,
                'factory' => null,
                'arguments' => [
                    '%kernel.project_dir%/composer.lock',
                    false,
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            KeysCommand::class => [
                'class' => KeysCommand::class,
                'factory' => null,
                'arguments' => [],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            PullCommand::class => [
                'class' => PullCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Application\Console\Puller',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            PurgeCommand::class => [
                'class' => PurgeCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository',
                    '@clock',
                    30,
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            PushCommand::class => [
                'class' => PushCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            SiteAddCommand::class => [
                'class' => SiteAddCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            SiteRemoveCommand::class => [
                'class' => SiteRemoveCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            UpdatePlanCommand::class => [
                'class' => UpdatePlanCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Application\Console\UpdatePlans',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            UpdateRunCommand::class => [
                'class' => UpdateRunCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\UpdatePlans',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            DoctrineConfigurationPushRepository::class => [
                'class' => DoctrineConfigurationPushRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            DoctrineJournalRepository::class => [
                'class' => DoctrineJournalRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            DoctrineSiteRepository::class => [
                'class' => DoctrineSiteRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            DoctrineSnapshotRepository::class => [
                'class' => DoctrineSnapshotRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            DoctrineUpdatePlanRepository::class => [
                'class' => DoctrineUpdatePlanRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            AgentController::class => [
                'class' => AgentController::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Agent\RequestVerifier',
                    '@Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints',
                    '@phil_sites_console.agent_key',
                    'shop',
                    '@logger?',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [
                    'controller.service_arguments',
                ],
            ],
            ConsoleController::class => [
                'class' => ConsoleController::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\Fleet',
                    '@Phil\SitesConsoleBundle\Application\Console\Puller',
                    '@Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes',
                    '@Phil\SitesConsoleBundle\Application\Console\UpdatePlans',
                    '@Phil\SitesConsoleBundle\Application\Console\Journal',
                    '@twig',
                    '@security.authorization_checker',
                    '@security.csrf.token_manager',
                    '@router',
                    'ROLE_SITES_CONSOLE_VIEWER',
                    'ROLE_SITES_CONSOLE_OPERATOR',
                    '@PhilSitesConsole/layout.html.twig',
                    '@security.token_storage?',
                    '@messenger.default_bus?',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [
                    'controller.service_arguments',
                ],
            ],
            HttpAgentClient::class => [
                'class' => HttpAgentClient::class,
                'factory' => null,
                'arguments' => [
                    '@app.http',
                    '@Phil\SitesConsoleBundle\Application\Console\ConsoleSigning',
                    '@clock',
                    10.0,
                    600.0,
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            DeliverConfigurationPushHandler::class => [
                'class' => DeliverConfigurationPushHandler::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [
                    'messenger.message_handler',
                ],
            ],
            PullSiteHandler::class => [
                'class' => PullSiteHandler::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\Puller',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [
                    'messenger.message_handler',
                ],
            ],
            RunUpdatePlanHandler::class => [
                'class' => RunUpdatePlanHandler::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\UpdatePlans',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [
                    'messenger.message_handler',
                ],
            ],
            KitComponents::class => [
                'class' => KitComponents::class,
                'factory' => null,
                'arguments' => [
                    'kit',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            SitesConsoleExtension::class => [
                'class' => SitesConsoleExtension::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Infrastructure\Twig\KitComponents',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [
                    'twig.attribute_extension',
                    'twig.runtime',
                ],
            ],
            'alias Phil\SitesConsoleBundle\Application\Contract\AgentClient' => [
                'to' => HttpAgentClient::class,
                'public' => false,
            ],
            'alias Phil\SitesConsoleBundle\Application\Contract\InstalledPackages' => [
                'to' => LockFilePackages::class,
                'public' => false,
            ],
            'alias Phil\SitesConsoleBundle\Application\Contract\NonceStore' => [
                'to' => CacheNonceStore::class,
                'public' => false,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository' => [
                'to' => DoctrineConfigurationPushRepository::class,
                'public' => true,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\JournalRepository' => [
                'to' => DoctrineJournalRepository::class,
                'public' => true,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\SiteRepository' => [
                'to' => DoctrineSiteRepository::class,
                'public' => true,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository' => [
                'to' => DoctrineSnapshotRepository::class,
                'public' => true,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository' => [
                'to' => DoctrineUpdatePlanRepository::class,
                'public' => true,
            ],
            'phil_sites_console.agent_key' => [
                'class' => SigningKey::class,
                'factory' => [
                    SigningKey::class,
                    'fromBase64',
                ],
                'arguments' => [
                    'ICEiIyQlJicoKSorLC0uLzAxMjM0NTY3ODk6Ozw9Pj8',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.console_key' => [
                'class' => SigningKey::class,
                'factory' => [
                    SigningKey::class,
                    'fromBase64',
                ],
                'arguments' => [
                    'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.console_public_key' => [
                'class' => VerifyingKey::class,
                'factory' => [
                    VerifyingKey::class,
                    'fromBase64',
                ],
                'arguments' => [
                    'PUBLIC',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.idempotency' => [
                'class' => IdempotencyGuard::class,
                'factory' => null,
                'arguments' => [
                    '@phil_sites_console.idempotency_store',
                    '@clock',
                    604800,
                    900,
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.idempotency_store' => [
                'class' => CacheIdempotencyStore::class,
                'factory' => null,
                'arguments' => [
                    '@app.pool',
                    '@app.locks',
                    '@clock',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            ConsoleKeyRing::class => [
                'class' => ConsoleKeyRing::class,
                'factory' => null,
                'arguments' => [
                    '@phil_sites_console.console_public_key',
                    '@phil_sites_console.console_next_public_key',
                    '@Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore',
                    '@logger?',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            CacheConsoleKeyStore::class => [
                'class' => CacheConsoleKeyStore::class,
                'factory' => null,
                'arguments' => [
                    '@app.keys',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            ConsoleSigning::class => [
                'class' => ConsoleSigning::class,
                'factory' => null,
                'arguments' => [
                    '@phil_sites_console.console_key',
                    '@phil_sites_console.console_next_key',
                    '@Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            KeyRotations::class => [
                'class' => KeyRotations::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\SiteRegistry',
                    '@Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository',
                    '@Phil\SitesConsoleBundle\Application\Contract\AgentClient',
                    '@Phil\SitesConsoleBundle\Application\Console\ConsoleSigning',
                    '@Phil\SitesConsoleBundle\Application\Console\Journal',
                    '@clock',
                ],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
            KeysRotateCommand::class => [
                'class' => KeysRotateCommand::class,
                'factory' => null,
                'arguments' => [
                    '@Phil\SitesConsoleBundle\Application\Console\KeyRotations',
                    '@Phil\SitesConsoleBundle\Application\Console\ConsoleSigning',
                ],
                'public' => false,
                'autoconfigured' => true,
                'tags' => [],
            ],
            DoctrineKeyRotationRepository::class => [
                'class' => DoctrineKeyRotationRepository::class,
                'factory' => null,
                'arguments' => [
                    '@doctrine.orm.sites_entity_manager',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.console_next_key' => [
                'class' => SigningKey::class,
                'factory' => [
                    SigningKey::class,
                    'fromBase64',
                ],
                'arguments' => [
                    'QEFCQ0RFRkdISUpLTE1OT1BRUlNUVVZXWFlaW1xdXl8',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'phil_sites_console.console_next_public_key' => [
                'class' => VerifyingKey::class,
                'factory' => [
                    VerifyingKey::class,
                    'fromBase64',
                ],
                'arguments' => [
                    'NEXT',
                ],
                'public' => false,
                'autoconfigured' => false,
                'tags' => [],
            ],
            'alias Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore' => [
                'to' => CacheConsoleKeyStore::class,
                'public' => false,
            ],
            'alias Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository' => [
                'to' => DoctrineKeyRotationRepository::class,
                'public' => true,
            ],
            'service_container' => [
                'class' => ContainerInterface::class,
                'factory' => null,
                'arguments' => [],
                'public' => true,
                'autoconfigured' => false,
                'tags' => [],
            ],
        ];
        ksort($all);

        return $all;
    }
}
