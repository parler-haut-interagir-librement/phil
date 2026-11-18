<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle;

use Override;
use Phil\ApiContractBundle\Application\Library;
use Phil\ApiContractBundle\Infrastructure\Console\DeprecationsCommand;
use Phil\ApiContractBundle\Infrastructure\Console\VerifyCommand;
use Phil\ApiContractBundle\Infrastructure\DependencyInjection\CheckDeprecatedEndpoints;
use Phil\ApiContractBundle\Infrastructure\Deprecation\CacheObservedDeprecations;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use Phil\ApiContractBundle\Infrastructure\Http\ActiveTenantResolver;
use Phil\ApiContractBundle\Infrastructure\Http\AnonymousCallers;
use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecationListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyClientResolver;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsListener;
use Phil\ApiContractBundle\Infrastructure\Http\TokenAttributeClientResolver;
use Phil\ApiContractBundle\Infrastructure\HttpClient\DeprecationObservingHttpClient;
use Phil\ApiContractBundle\Infrastructure\Messenger\TenantIdempotencyScopeMiddleware;
use Phil\ApiContractBundle\Infrastructure\OpenApi\ContractVerifierFactory;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\AsyncDecoratorTrait;

/**
 * Both sides of an unversioned HTTP API contract in a Symfony application, on `phil/api-contract`: exceptions
 * answered as problem details; `#[Idempotent]` controllers on the guard of `phil/idempotency`, their keys kept in a table of a Doctrine connection
 * or in a cache pool under a lock;
 * `#[DeprecatedEndpoint]` controllers that announce their sunset; and, as a client, the deprecations of the APIs it
 * calls, recorded, logged once a day, and listed by a command that fails in CI while one has a sunset. Its own
 * OpenAPI document is verified against a level of rules by `phil:api-contract:verify`.
 */
final class PhilApiContractBundle extends AbstractBundle
{
    /**
     * The tag of the application's own rules of verification, which every service implementing the library's
     * `OpenApiRule` receives.
     */
    public const string RULE_TAG = 'phil_api_contract.openapi_rule';

    #[Override]
    protected string $extensionAlias = 'phil_api_contract';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CheckDeprecatedEndpoints());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('problem_details')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->info('Regular expressions on the path under which every exception is answered as a problem: "^/api/".')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('idempotency')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('ttl')
                            ->info('How many seconds an answered key is remembered.')
                            ->min(1)
                            ->defaultValue(86_400)
                        ->end()
                        ->integerNode('reservation_ttl')
                            ->info('How many seconds a key stays reserved before its answer: the wait after a crash. Longer than the slowest request.')
                            ->min(1)
                            ->defaultValue(300)
                        ->end()
                        ->enumNode('anonymous')
                            ->info('What an #[Idempotent] endpoint does with a caller that is neither a user nor a client: "refuse" answers a 403; "ip" scopes it by its address, shared by everyone behind that address.')
                            ->values(array_map(static fn (AnonymousCallers $case): string => $case->value, AnonymousCallers::cases()))
                            ->defaultValue(AnonymousCallers::Refuse->value)
                        ->end()
                        ->scalarNode('client_attribute')
                            ->info('The attribute of the security token that names the API client, part of the scope of a key; null reads none.')
                            ->defaultValue(TokenAttributeClientResolver::DEFAULT_ATTRIBUTE)
                        ->end()
                        ->arrayNode('replayed_headers')
                            ->info('The response headers kept and replayed with the body.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['Content-Type', 'Location'])
                        ->end()
                        ->scalarNode('connection')
                            ->info('The Doctrine connection that keeps the keys; null keeps them elsewhere.')
                            ->defaultValue('default')
                        ->end()
                        ->scalarNode('cache_pool')
                            ->info('A cache pool that keeps the keys instead of the connection, shared by every server: "cache.idempotency".')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('lock_factory')
                            ->info('The lock factory that makes a reservation in the cache pool atomic, on a store shared by every server.')
                            ->defaultValue('lock.factory')
                        ->end()
                        ->arrayNode('messenger')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('tenant_scope')
                                    ->info('Registers the Messenger middleware that puts the tenant of a received message into the scope of its IdempotencyStamp; needs PhilTenancyBundle.')
                                    ->defaultFalse()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('verification')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('document')
                            ->info('The OpenAPI document phil:api-contract:verify reads when none is named: "%kernel.project_dir%/openapi.yaml".')
                            ->defaultNull()
                        ->end()
                        ->enumNode('level')
                            ->info('The rules run when no --level is given.')
                            ->values(['minimal', 'recommended', 'strict'])
                            ->defaultValue('recommended')
                        ->end()
                        ->enumNode('fail_on')
                            ->info('The severity that fails the command when no --fail-on is given.')
                            ->values(['error', 'warning'])
                            ->defaultValue('error')
                        ->end()
                        ->arrayNode('skip')
                            ->info('The ids of the rules left out: "operation-description".')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Without DoctrineBundle, the keys are kept elsewhere: the application's own store.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('phil_api_contract', ['idempotency' => ['connection' => null]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{problem_details: array{paths: list<string>}, idempotency: array{ttl: int, reservation_ttl: int, anonymous: string, client_attribute: ?string, replayed_headers: list<string>, connection: ?string, cache_pool: ?string, lock_factory: string, messenger: array{tenant_scope: bool}}, verification: array{document: ?string, level: string, fail_on: string, skip: list<string>}} $config */
        $services = $container->services();
        $clock = new Reference('clock');

        $services->set(ProblemDetailsListener::class)
            ->args([$config['problem_details']['paths'], '%kernel.debug%'])
            ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

        $tokens = new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $services->set(ActiveTenantResolver::class)
            ->args([new Reference(TenantContext::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $services->alias(IdempotencyTenantResolver::class, ActiveTenantResolver::class);
        $services->set(TokenAttributeClientResolver::class)
            ->args([$tokens, $config['idempotency']['client_attribute']]);
        $services->alias(IdempotencyClientResolver::class, TokenAttributeClientResolver::class);
        $services->set(CallerScopeResolver::class)
            ->args([$tokens, new Reference(IdempotencyTenantResolver::class), new Reference(IdempotencyClientResolver::class), AnonymousCallers::from($config['idempotency']['anonymous'])]);
        $services->alias(IdempotencyScopeResolver::class, CallerScopeResolver::class);
        $services->set(IdempotencyListener::class)
            ->args([new Reference(Library::IDEMPOTENCY_STORE, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(IdempotencyScopeResolver::class), $config['idempotency']['ttl'], $clock, $config['idempotency']['replayed_headers'], $config['idempotency']['reservation_ttl']])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller_arguments', 'method' => 'onControllerArguments', 'priority' => IdempotencyListener::PRIORITY])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

        if ($config['idempotency']['messenger']['tenant_scope']) {
            $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

            if (!\is_array($bundles) || !isset($bundles['PhilTenancyBundle'])) {
                throw new InvalidConfigurationException('phil_api_contract.idempotency.messenger.tenant_scope reads the TenantStamp of phil/tenancy-bundle: enable PhilTenancyBundle, or turn the option off.');
            }

            $services->set(TenantIdempotencyScopeMiddleware::class);
        }

        $services->set(DeprecationListener::class)
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

        $services->set(CacheObservedDeprecations::class)
            ->args([new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $services->alias(Library::OBSERVED_DEPRECATIONS, CacheObservedDeprecations::class)->public();

        if (trait_exists(AsyncDecoratorTrait::class)) {
            $services->set(DeprecationObservingHttpClient::class)
                ->decorate('http_client', invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->args([new Reference('.inner'), new Reference(Library::OBSERVED_DEPRECATIONS), $clock, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $builder->registerForAutoconfiguration(Library::OPENAPI_RULE)->addTag(self::RULE_TAG);
        $services->set(Library::CONTRACT_VERIFIER)
            ->factory(ContractVerifierFactory::create(...))
            ->args([new TaggedIteratorArgument(self::RULE_TAG), $config['verification']['skip']])
            ->public();

        if (class_exists(Command::class)) {
            $services->set(DeprecationsCommand::class)
                ->args([new Reference(Library::OBSERVED_DEPRECATIONS), $clock])
                ->tag('console.command');
            $services->set(VerifyCommand::class)
                ->args([new Reference(Library::CONTRACT_VERIFIER), $config['verification']['document'], $config['verification']['level'], $config['verification']['fail_on']])
                ->tag('console.command');
        }

        $connection = $config['idempotency']['connection'];
        $pool = $config['idempotency']['cache_pool'];

        if (\is_string($pool)) {
            $services->set(CacheIdempotencyStore::class)->args([new Reference($pool), new Reference($config['idempotency']['lock_factory']), $clock]);
            $services->alias(Library::IDEMPOTENCY_STORE, CacheIdempotencyStore::class)->public();
        } elseif (\is_string($connection)) {
            $reference = new Reference(\sprintf('doctrine.dbal.%s_connection', $connection));
            $services->set(DbalIdempotencyStore::class)->args([$reference, $clock]);
            $services->alias(Library::IDEMPOTENCY_STORE, DbalIdempotencyStore::class)->public();
            $services->set(IdempotencySchemaListener::class)
                ->args([$reference])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $connection]);
        }
    }
}
