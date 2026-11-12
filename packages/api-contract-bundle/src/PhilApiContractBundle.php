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
use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecationListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsListener;
use Phil\ApiContractBundle\Infrastructure\HttpClient\DeprecationObservingHttpClient;
use Phil\ApiContractBundle\Infrastructure\OpenApi\ContractVerifierFactory;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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
        /** @var array{problem_details: array{paths: list<string>}, idempotency: array{ttl: int, reservation_ttl: int, replayed_headers: list<string>, connection: ?string, cache_pool: ?string, lock_factory: string}, verification: array{document: ?string, level: string, fail_on: string, skip: list<string>}} $config */
        $services = $container->services();
        $clock = new Reference('clock');

        $services->set(ProblemDetailsListener::class)
            ->args([$config['problem_details']['paths'], '%kernel.debug%'])
            ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

        $services->set(CallerScopeResolver::class)
            ->args([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $services->alias(IdempotencyScopeResolver::class, CallerScopeResolver::class);
        // A replay replaces the controller: the listeners that read the route's controller — the deprecation marker,
        // the profiler's request collector — see it first, so that a replayed response still announces its
        // deprecation and the profiler names the real controller.
        $services->set(IdempotencyListener::class)
            ->args([new Reference(Library::IDEMPOTENCY_STORE, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(IdempotencyScopeResolver::class), $config['idempotency']['ttl'], $clock, $config['idempotency']['replayed_headers'], $config['idempotency']['reservation_ttl']])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController', 'after' => [DeprecationListener::class, 'data_collector.request']])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

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
