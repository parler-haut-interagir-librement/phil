<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Lti\Contract\AccessTokens;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\LineItemStore;
use Phil\Lti\Contract\LoginStates;
use Phil\Lti\Contract\Nonces;
use Phil\Lti\Contract\PlatformRegistrations;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Key\CachedKeySetSource;
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Ags\InMemoryLineItemStore;
use Phil\Lti\Platform\Ags\InMemoryScoreStore;
use Phil\Lti\Platform\DeepLinkingReturn;
use Phil\Lti\Platform\LaunchIssuer;
use Phil\Lti\Platform\LaunchStarter;
use Phil\Lti\Platform\Nrps\NrpsService;
use Phil\Lti\Platform\Token\InMemoryAccessTokens;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Platform\Token\TokenEndpoint;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Security\InMemoryLoginStates;
use Phil\Lti\Security\InMemoryNonces;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Nrps\NrpsClient;
use Phil\Lti\Service\ServiceCall;
use Phil\Lti\Tool\DeepLinkingResponder;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\Contract\KeySealer;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Application\Contract\LaunchUsers;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\DeepLinkingHandler;
use Phil\LtiBundle\Application\ScorePublisher;
use Phil\LtiBundle\Infrastructure\Configuration\Registrations;
use Phil\LtiBundle\Infrastructure\Console\PurgeCommand;
use Phil\LtiBundle\Infrastructure\Console\RecoverScoresCommand;
use Phil\LtiBundle\Infrastructure\Console\ResumeDeepLinkingCommand;
use Phil\LtiBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Controller\AgsController;
use Phil\LtiBundle\Infrastructure\Controller\JwksController;
use Phil\LtiBundle\Infrastructure\Controller\NrpsController;
use Phil\LtiBundle\Infrastructure\Controller\PlatformLaunchController;
use Phil\LtiBundle\Infrastructure\Controller\TokenController;
use Phil\LtiBundle\Infrastructure\Controller\ToolController;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore;
use Phil\LtiBundle\Infrastructure\Dbal\LtiSchemaListener;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\Http\FormPostResponder;
use Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores;
use Phil\LtiBundle\Infrastructure\Messenger\PublishScoreHandler;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator;
use Phil\LtiBundle\Infrastructure\Security\LtiUserProvider;
use Phil\LtiBundle\Infrastructure\Twig\KitComponents;
use Phil\LtiBundle\Infrastructure\Twig\LtiExtension;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the configuration: what both sides share — the stores of the nonces and the keys, the key sets of the
 * other side, the verifier, the key set route, the form post page —, then the tool's and the platform's when enabled.
 *
 * @phpstan-import-type PlatformConfig from Registrations
 * @phpstan-import-type ToolConfig from Registrations
 *
 * @phpstan-type KeysConfig array{source: string, kms: string, master_key: ?string, lifetime: int, overlap: int, bits: int, private_key: ?string, passphrase: ?string, key_id: ?string, retired_public_keys: list<string>}
 * @phpstan-type LtiConfig array{store: string, connection: string, table_prefix: string, clock: string, cache: string, http_client: ?string, message_bus: ?string, allow_localhost: bool, kit_subdirectory: ?string, keys: KeysConfig, tool: array{enabled: bool, state_lifetime: int, refused_score_retention: int, platforms: list<PlatformConfig>}, platform: array{enabled: bool, issuer: ?string, token_url: ?string, token_lifetime: int, deep_linking_retention: positive-int, tools: list<ToolConfig>}}
 */
final class LtiServices
{
    public const string STORE_DBAL = 'dbal';

    public const string STORE_MEMORY = 'memory';

    public const string KEYS_DBAL = 'dbal';

    public const string KEYS_CONFIG = 'config';

    /** The `phil/safe-http` client of the services, over the HTTP client of the configuration. */
    public const string SERVICE_HTTP_CLIENT = 'phil_lti.service_http_client';

    /** The `phil/safe-http` client of the key sets of the other side, over the HTTP client of the configuration. */
    public const string KEY_SET_HTTP_CLIENT = 'phil_lti.key_set_http_client';

    /** The parameter that says whether the platform side is enabled, for `DefaultsPass`. */
    public const string PLATFORM_PARAMETER = 'phil_lti.platform';

    /**
     * @param LtiConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $dbal = self::STORE_DBAL === $config['store'];
        $connection = $dbal ? new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection'])) : null;
        $clock = new Reference($config['clock']);
        $localhost = $config['allow_localhost'];
        $container->setParameter(self::PLATFORM_PARAMETER, $config['platform']['enabled']);

        $container->register(LtiTables::class, LtiTables::class)->setArguments([$config['table_prefix']]);
        $tables = new Reference(LtiTables::class);
        self::store($container, Nonces::class, $dbal ? DbalNonces::class : InMemoryNonces::class, $dbal ? [$connection, $tables, $clock] : [$clock]);
        self::keys($container, $config, $connection, $tables, $clock);

        $httpClient = null === $config['http_client'] ? null : new Reference($config['http_client']);
        $container->register(self::KEY_SET_HTTP_CLIENT, SafeHttpClient::class)->setArguments([
            $httpClient,
            new Definition(Limits::class)->setFactory([SafeHttpKeySetSource::class, 'defaultLimits']),
        ]);
        $container->register(SafeHttpKeySetSource::class, SafeHttpKeySetSource::class)
            ->setArguments([new Reference(self::KEY_SET_HTTP_CLIENT)])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(CachedKeySetSource::class, CachedKeySetSource::class)->setArguments([new Reference(SafeHttpKeySetSource::class), new Reference($config['cache']), $clock]);
        $container->setAlias(KeySetSource::class, CachedKeySetSource::class);
        $container->register(JwtVerifier::class, JwtVerifier::class)->setArguments([new Reference(KeySetSource::class), $clock]);
        $container->register(self::SERVICE_HTTP_CLIENT, SafeHttpClient::class)->setArguments([
            $httpClient,
            new Definition(Limits::class)->setFactory([SafeHttpServiceClient::class, 'defaultLimits']),
        ]);
        $container->register(SafeHttpServiceClient::class, SafeHttpServiceClient::class)
            ->setArguments([new Reference(self::SERVICE_HTTP_CLIENT)])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(ServiceClient::class, SafeHttpServiceClient::class);

        $container->register(JwksController::class, JwksController::class)->setArguments([new Reference(KeyStore::class)])->setPublic(true)->addTag('controller.service_arguments');
        $container->register(FormPostResponder::class, FormPostResponder::class)->setArguments([new Reference('twig')])->setPublic(true);
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(LtiExtension::class, LtiExtension::class)->setArguments([new Reference(KitComponents::class)])->addTag('twig.attribute_extension')->addTag('twig.runtime');

        if ($config['tool']['enabled']) {
            self::tool($container, $config, $connection, $tables, $clock, $localhost);
        }

        if ($config['platform']['enabled']) {
            self::platform($container, $config, $connection, $tables, $clock, $localhost);
        }

        if (!$connection instanceof Reference) {
            return;
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(LtiSchemaListener::class, LtiSchemaListener::class)
                ->setArguments([$connection, $tables])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (class_exists(Command::class)) {
            $container->register(SchemaCommand::class, SchemaCommand::class)->setArguments([$connection, $tables])->addTag('console.command');
            $container->register(PurgeCommand::class, PurgeCommand::class)
                ->setArguments([
                    new Reference(DbalNonces::class),
                    $clock,
                    new Reference(DbalLoginStates::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(DbalAccessTokens::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(PendingScores::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(DeepLinkingReturns::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $config['tool']['refused_score_retention'],
                ])
                ->addTag('console.command');
        }
    }

    /**
     * @param LtiConfig $config
     */
    private static function keys(ContainerBuilder $container, array $config, ?Reference $connection, Reference $tables, Reference $clock): void
    {
        $keys = $config['keys'];

        if (self::KEYS_CONFIG === $keys['source'] || !$connection instanceof Reference) {
            $container->register(InMemoryKeyStore::class, InMemoryKeyStore::class)
                ->setFactory([Registrations::class, 'keys'])
                ->setArguments([$keys['private_key'] ?? '', $keys['key_id'], $keys['retired_public_keys'], $keys['passphrase']]);
            $container->setAlias(KeyStore::class, InMemoryKeyStore::class)->setPublic(true);

            return;
        }

        $container->register(KeyManagementSealer::class, KeyManagementSealer::class)->setArguments([new Reference($keys['kms']), (string) $keys['master_key']]);
        $container->setAlias(KeySealer::class, KeyManagementSealer::class);
        $container->register(DbalKeyStore::class, DbalKeyStore::class)
            ->setArguments([$connection, $tables, new Reference(KeySealer::class), $clock, $keys['overlap'], $keys['bits']])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(KeyStore::class, DbalKeyStore::class)->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(RotateKeysCommand::class, RotateKeysCommand::class)->setArguments([new Reference(DbalKeyStore::class), $keys['lifetime']])->addTag('console.command');
        }
    }

    /**
     * @param LtiConfig $config
     */
    private static function tool(ContainerBuilder $container, array $config, ?Reference $connection, Reference $tables, Reference $clock, bool $localhost): void
    {
        $dbal = $connection instanceof Reference;
        $container->register(InMemoryPlatformRegistrations::class, InMemoryPlatformRegistrations::class)
            ->setFactory([Registrations::class, 'platforms'])
            ->setArguments([$config['tool']['platforms'], $localhost]);
        $container->setAlias(PlatformRegistrations::class, InMemoryPlatformRegistrations::class)->setPublic(true);
        self::store($container, LoginStates::class, $dbal ? DbalLoginStates::class : InMemoryLoginStates::class, $dbal ? [$connection, $tables, $clock] : [$clock]);
        self::store($container, PendingScores::class, $dbal ? DbalPendingScores::class : InMemoryPendingScores::class, $dbal ? [$connection, $tables, $localhost] : []);

        $container->register(LoginInitiator::class, LoginInitiator::class)
            ->setArguments([new Reference(PlatformRegistrations::class), new Reference(LoginStates::class), $clock, $config['tool']['state_lifetime'], $localhost]);
        $container->register(LaunchValidator::class, LaunchValidator::class)
            ->setArguments([new Reference(PlatformRegistrations::class), new Reference(LoginStates::class), new Reference(Nonces::class), new Reference(JwtVerifier::class), $localhost]);
        $container->register(DeepLinkingResponder::class, DeepLinkingResponder::class)->setArguments([new Reference(KeyStore::class), $clock])->setPublic(true);
        $container->register(AccessTokenProvider::class, AccessTokenProvider::class)
            ->setArguments([new Reference(ServiceClient::class), new Reference(KeyStore::class), new Reference($config['cache']), $clock]);
        $container->register(ServiceCall::class, ServiceCall::class)->setArguments([new Reference(ServiceClient::class), new Reference(AccessTokenProvider::class)]);
        $container->register(AgsClient::class, AgsClient::class)->setArguments([new Reference(ServiceCall::class), $localhost])->setPublic(true);
        $container->register(NrpsClient::class, NrpsClient::class)->setArguments([new Reference(ServiceCall::class), $localhost])->setPublic(true);
        $container->register(ScorePublisher::class, ScorePublisher::class)
            ->setArguments([
                new Reference(PendingScores::class),
                new Reference(PlatformRegistrations::class),
                new Reference(AgsClient::class),
                $clock,
                null === $config['message_bus'] ? null : new Reference($config['message_bus']),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(PublishScoreHandler::class, PublishScoreHandler::class)
            ->setArguments([new Reference(ScorePublisher::class)])
            ->addTag('messenger.message_handler');

        if (class_exists(Command::class)) {
            $container->register(RecoverScoresCommand::class, RecoverScoresCommand::class)->setArguments([new Reference(ScorePublisher::class)])->addTag('console.command');
        }

        $container->register(LtiUserProvider::class, LtiUserProvider::class);
        $container->register(LtiLaunchAuthenticator::class, LtiLaunchAuthenticator::class)
            ->setArguments([new Reference(LaunchValidator::class), new Reference(LaunchUsers::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(ToolController::class, ToolController::class)
            ->setArguments([new Reference(LoginInitiator::class), new Reference('router'), new Reference('security.token_storage'), new Reference('event_dispatcher'), $localhost])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * @param LtiConfig $config
     */
    private static function platform(ContainerBuilder $container, array $config, ?Reference $connection, Reference $tables, Reference $clock, bool $localhost): void
    {
        $dbal = $connection instanceof Reference;
        $issuer = (string) $config['platform']['issuer'];
        $container->register(InMemoryToolRegistrations::class, InMemoryToolRegistrations::class)
            ->setFactory([Registrations::class, 'tools'])
            ->setArguments([$config['platform']['tools'], $localhost]);
        $container->setAlias(ToolRegistrations::class, InMemoryToolRegistrations::class)->setPublic(true);
        self::store($container, AccessTokens::class, $dbal ? DbalAccessTokens::class : InMemoryAccessTokens::class, $dbal ? [$connection, $tables, $clock] : [$clock]);
        self::store($container, LineItemStore::class, $dbal ? DbalLineItemStore::class : InMemoryLineItemStore::class, $dbal ? [$connection, $tables] : []);
        self::store($container, ScoreStore::class, $dbal ? DbalScoreStore::class : InMemoryScoreStore::class, $dbal ? [$connection, $tables] : []);
        self::store($container, DeepLinkingReturns::class, $dbal ? DbalDeepLinkingReturns::class : InMemoryDeepLinkingReturns::class, $dbal ? [$connection, $tables] : []);
        $container->register(DeepLinkingHandler::class, DeepLinkingHandler::class)
            ->setArguments([new Reference(DeepLinkingReturns::class), new Reference('event_dispatcher'), $clock, $config['platform']['deep_linking_retention'], $localhost])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(ResumeDeepLinkingCommand::class, ResumeDeepLinkingCommand::class)->setArguments([new Reference(DeepLinkingHandler::class)])->addTag('console.command');
        }

        $container->register(PlatformUrls::class, PlatformUrls::class)->setArguments([new Reference('router'), $localhost])->setPublic(true);
        $container->register(LaunchStarter::class, LaunchStarter::class)->setArguments([$issuer, new Reference(ToolRegistrations::class), $localhost])->setPublic(true);
        $container->register(LaunchIssuer::class, LaunchIssuer::class)->setArguments([$issuer, new Reference(ToolRegistrations::class), new Reference(KeyStore::class), $clock, $localhost]);
        $container->register(DeepLinkingReturn::class, DeepLinkingReturn::class)->setArguments([$issuer, new Reference(ToolRegistrations::class), new Reference(Nonces::class), new Reference(JwtVerifier::class), $localhost]);
        $container->register(TokenEndpoint::class, TokenEndpoint::class)
            ->setArguments([
                (string) $config['platform']['token_url'],
                new Reference(ToolRegistrations::class),
                new Reference(AccessTokens::class),
                new Reference(Nonces::class),
                new Reference(JwtVerifier::class),
                $clock,
                $config['platform']['token_lifetime'],
            ]);
        $container->register(ServiceGuard::class, ServiceGuard::class)->setArguments([new Reference(AccessTokens::class)]);
        $container->register(AgsService::class, AgsService::class)->setArguments([new Reference(Roster::class), new Reference(LineItemStore::class), new Reference(ScoreStore::class)]);
        $container->register(NrpsService::class, NrpsService::class)->setArguments([new Reference(Roster::class)]);

        $container->register(PlatformLaunchController::class, PlatformLaunchController::class)
            ->setArguments([new Reference(LaunchIssuer::class), new Reference(LaunchMessages::class), new Reference(DeepLinkingReturn::class), new Reference('security.token_storage'), new Reference(FormPostResponder::class), new Reference(DeepLinkingHandler::class), new Reference('twig')])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(TokenController::class, TokenController::class)->setArguments([new Reference(TokenEndpoint::class)])->setPublic(true)->addTag('controller.service_arguments');
        $container->register(AgsController::class, AgsController::class)
            ->setArguments([new Reference(ServiceGuard::class), new Reference(AgsService::class), new Reference(PlatformUrls::class), new Reference('event_dispatcher')])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(NrpsController::class, NrpsController::class)
            ->setArguments([new Reference(ServiceGuard::class), new Reference(NrpsService::class), new Reference(PlatformUrls::class)])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * @param class-string                     $port
     * @param class-string                     $adapter
     * @param list<Reference|string|bool|null> $arguments
     */
    private static function store(ContainerBuilder $container, string $port, string $adapter, array $arguments): void
    {
        $definition = $container->register($adapter, $adapter)->setArguments($arguments);

        if (str_contains($adapter, 'InMemory')) {
            $definition->addTag('kernel.reset', ['method' => 'reset']);
        }

        $container->setAlias($port, $adapter)->setPublic(true);
    }
}
