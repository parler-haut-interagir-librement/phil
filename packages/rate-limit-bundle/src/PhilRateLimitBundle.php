<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle;

use Override;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\LimitersPass;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\RateLimitServices;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Rate limits declared on controllers, routes and forms:
 *
 * ```yaml
 * phil_rate_limit:
 *     limiters:
 *         api: { policy: sliding_window, limit: 100, interval: '1 minute' }
 *         login: { policy: fixed_window, limit: 5, interval: '15 minutes', key: ip_route }
 *         forms: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes' } }
 *         strict: { policy: compound, limiters: [api, login] }
 *         search: { policy: sliding_window, limit: 30, interval: '1 minute', observe: true }
 *     forms:
 *         contact: forms
 * ```
 *
 * The bundle builds the limiters over `symfony/rate-limiter`, their state in a cache pool, and collects those of other
 * packages tagged `phil_rate_limit.limiter`. `#[RateLimit]`, the route default `_rate_limit` and the form option
 * `rate_limit` name them; a refusal is answered `429 Too Many Requests` with `Retry-After` and the rate-limit headers,
 * as a problem in JSON to an API, a Twig page to the others.
 */
final class PhilRateLimitBundle extends AbstractBundle
{
    public const string TEMPLATE = '@PhilRateLimit/too_many_requests.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_rate_limit';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('secret')
                ->info('The key of the hashes the ip, user and ip_route keys are kept as.')
                ->defaultValue('%kernel.secret%')
                ->cannotBeEmpty()
            ->end()
            ->enumNode('headers')
                ->info('The rate-limit headers: "draft" (RateLimit-Policy and RateLimit), "legacy" (RateLimit-Limit, -Remaining and -Reset), "both" or "none".')
                ->values(array_map(static fn (HeaderStyle $style): string => $style->value, HeaderStyle::cases()))
                ->defaultValue(HeaderStyle::Draft->value)
            ->end()
            ->scalarNode('error_template')->info('The Twig template of a refusal answered in HTML.')->defaultValue(self::TEMPLATE)->cannotBeEmpty()->end()
            ->scalarNode('cache_pool')->info('The cache pool the limiters keep their state in, unless a limiter names its own pool or storage.')->defaultValue('cache.app')->cannotBeEmpty()->end()
            ->scalarNode('lock_factory')->info('The lock factory of the limiters, for exact counts under concurrency; null for none.')->defaultNull()->end()
            ->booleanNode('single_server')->info('Accepts a pool or a storage local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
            ->arrayNode('network')
                ->info("The ip keys of a network rather than a host, with phil/ip-anonymizer: an IPv6 subscriber's /64 shares one key.")
                ->canBeEnabled()
                ->children()
                    ->integerNode('ipv4_prefix')->min(0)->max(32)->defaultValue(32)->end()
                    ->integerNode('ipv6_prefix')->min(0)->max(128)->defaultValue(64)->end()
                ->end()
            ->end();

        $this->limiters($children);

        $children
            ->arrayNode('forms')
                ->info('The rate limits of forms by name: a limiter\'s name, or a list of rules of "limiter", "amount" and "key".')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->variablePrototype()->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{secret: string, headers: string, error_template: string, cache_pool: string, lock_factory: string|null, single_server: bool, network: array{enabled: bool, ipv4_prefix: int, ipv6_prefix: int}, limiters: array<array-key, array{policy: string|null, service: string|null, limit: int|null, interval: string|null, rate: array{interval: string|null, amount: int}, anchor_at: string|null, limiters: list<string>, key: string, amount: int, cache_pool: string|null, storage: string|null, lock_factory: string|null, observe: bool}>, forms: array<array-key, mixed>} $config */
        RateLimitServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LimitersPass());
        $container->addCompilerPass(new RateLimitGuard());
    }

    private function limiters(NodeBuilder $children): void
    {
        $children
            ->arrayNode('limiters')
                ->info('The limiters, by name: a policy of symfony/rate-limiter with its options, or a factory service.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->enumNode('policy')
                            ->info('fixed_window, sliding_window, token_bucket, compound or no_limit; null with "service".')
                            ->values(['fixed_window', 'sliding_window', 'token_bucket', 'compound', 'no_limit', null])
                            ->defaultNull()
                        ->end()
                        ->scalarNode('service')->info('The id of a RateLimiterFactoryInterface service, such as one of the rate_limiter configuration, instead of a policy.')->defaultNull()->end()
                        ->integerNode('limit')->info('The hits a window allows, or the size of a token bucket.')->min(1)->defaultNull()->end()
                        ->scalarNode('interval')->info("A window's length, such as '1 minute' or '15 minutes'.")->defaultNull()->end()
                        ->arrayNode('rate')
                            ->info("A token bucket's refill: so many tokens each interval.")
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('interval')->defaultNull()->end()
                                ->integerNode('amount')->min(1)->defaultValue(1)->end()
                            ->end()
                        ->end()
                        ->scalarNode('anchor_at')->info('A fixed window aligned on the calendar from this moment, for windows of a month or more.')->defaultNull()->end()
                        ->arrayNode('limiters')->info('The limiters of a compound one, each counted.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->scalarNode('key')->info('Who a request counts for when a rule names no key: ip, user, user_or_ip, ip_route, or a KeyResolver service id.')->defaultValue('ip')->cannotBeEmpty()->end()
                        ->integerNode('amount')->info('What one request consumes when a rule names no amount.')->min(1)->defaultValue(1)->end()
                        ->scalarNode('cache_pool')->info("The cache pool of this limiter's state, instead of the bundle's.")->defaultNull()->end()
                        ->scalarNode('storage')->info("The id of a StorageInterface service of this limiter's state, instead of a cache pool.")->defaultNull()->end()
                        ->scalarNode('lock_factory')->info("The lock factory of this limiter, instead of the bundle's.")->defaultNull()->end()
                        ->booleanNode('observe')->info('Counts and reports without refusing: a refusal dispatches RateLimitObserved and logs a warning, and the request goes on.')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end();
    }
}
