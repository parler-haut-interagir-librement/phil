<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitHeaders;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\LimitersPass;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\RateLimitServices;
use Phil\RateLimitBundle\Infrastructure\Form\RateLimitTypeExtension;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitCacheWarmer;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitListener;
use Phil\RateLimitBundle\Infrastructure\Http\TooManyRequestsResponder;
use Phil\RateLimitBundle\Infrastructure\IpAnonymizer\NetworkAddress;
use Phil\RateLimitBundle\Infrastructure\Key\ClientIpKey;
use Phil\RateLimitBundle\Infrastructure\Key\IpRouteKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserKey;
use Phil\RateLimitBundle\Infrastructure\Key\UserOrIpKey;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

#[CoversClass(PhilRateLimitBundle::class)]
#[CoversClass(RateLimitServices::class)]
final class PhilRateLimitBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_rate_limit', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', RateLimitServices::IP_KEY, RateLimitServices::USER_KEY, RateLimitServices::USER_OR_IP_KEY, RateLimitServices::IP_ROUTE_KEY, RateLimitHeaders::class, Limiters::class, KeyResolvers::class, RateLimitEnforcer::class, RateLimitListener::class, RateLimitCacheWarmer::class, TooManyRequestsResponder::class, RateLimitTypeExtension::class],
            array_keys($container->getDefinitions()),
        );
        self::assertSame([], $container->getParameter(RateLimitServices::REQUIRED_PARAMETER));
        self::assertSame([], $container->getParameter(RateLimitServices::FORMS_PARAMETER));

        self::assertSame(ClientIpKey::class, $container->getDefinition(RateLimitServices::IP_KEY)->getClass());
        self::assertSame(['%kernel.secret%', null], $container->getDefinition(RateLimitServices::IP_KEY)->getArguments());
        self::assertSame([RateLimitServices::KEY_TAG => [['strategy' => 'ip']]], $container->getDefinition(RateLimitServices::IP_KEY)->getTags());
        self::assertSame(UserKey::class, $container->getDefinition(RateLimitServices::USER_KEY)->getClass());
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), '%kernel.secret%'], $container->getDefinition(RateLimitServices::USER_KEY)->getArguments());
        self::assertSame([RateLimitServices::KEY_TAG => [['strategy' => 'user']]], $container->getDefinition(RateLimitServices::USER_KEY)->getTags());
        self::assertSame(UserOrIpKey::class, $container->getDefinition(RateLimitServices::USER_OR_IP_KEY)->getClass());
        self::assertEquals([new Reference(RateLimitServices::USER_KEY), new Reference(RateLimitServices::IP_KEY)], $container->getDefinition(RateLimitServices::USER_OR_IP_KEY)->getArguments());
        self::assertSame([RateLimitServices::KEY_TAG => [['strategy' => 'user_or_ip']]], $container->getDefinition(RateLimitServices::USER_OR_IP_KEY)->getTags());
        self::assertSame(IpRouteKey::class, $container->getDefinition(RateLimitServices::IP_ROUTE_KEY)->getClass());
        self::assertEquals([new Reference(RateLimitServices::IP_KEY), '%kernel.secret%'], $container->getDefinition(RateLimitServices::IP_ROUTE_KEY)->getArguments());
        self::assertSame([RateLimitServices::KEY_TAG => [['strategy' => 'ip_route']]], $container->getDefinition(RateLimitServices::IP_ROUTE_KEY)->getTags());
        self::assertSame([RateLimitServices::KEY_TAG => [[]]], $container->getAutoconfiguredInstanceof()[KeyResolver::class]->getTags());

        self::assertEquals([HeaderStyle::Draft], $container->getDefinition(RateLimitHeaders::class)->getArguments());
        self::assertSame([null, []], $container->getDefinition(Limiters::class)->getArguments());
        self::assertSame([null, []], $container->getDefinition(KeyResolvers::class)->getArguments());

        $enforcer = $container->getDefinition(RateLimitEnforcer::class);
        self::assertEquals([new Reference(Limiters::class), new Reference(KeyResolvers::class), new Reference(RateLimitHeaders::class), new Reference('event_dispatcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $enforcer->getArguments());
        self::assertSame([['channel' => 'rate_limit']], $enforcer->getTag('monolog.logger'));
        self::assertTrue($enforcer->isPublic());

        $listener = $container->getDefinition(RateLimitListener::class);
        self::assertEquals([new Reference(RateLimitEnforcer::class)], $listener->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.controller', 'method' => 'onKernelController'], ['event' => 'kernel.response', 'method' => 'onKernelResponse']]], $listener->getTags());

        $responder = $container->getDefinition(TooManyRequestsResponder::class);
        self::assertEquals([new Reference('twig', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), '@PhilRateLimit/too_many_requests.html.twig', new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $responder->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.exception', 'method' => 'onKernelException']]], $responder->getTags());

        $forms = $container->getDefinition(RateLimitTypeExtension::class);
        self::assertEquals([new Reference(RateLimitEnforcer::class), new Reference('request_stack'), [], new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $forms->getArguments());
        self::assertSame(['form.type_extension' => [[]]], $forms->getTags());
    }

    public function testTheLimitersOfEachPolicy(): void
    {
        $container = $this->load([
            'secret' => 'pepper',
            'headers' => 'both',
            'error_template' => 'errors/429.html.twig',
            'cache_pool' => 'cache.limits',
            'lock_factory' => 'lock.factory',
            'network' => ['enabled' => true, 'ipv4_prefix' => 24],
            'limiters' => [
                'api' => ['policy' => 'sliding_window', 'limit' => 100, 'interval' => '1 minute', 'key' => 'user_or_ip', 'amount' => 2],
                'my-api' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 second', 'amount' => 1],
                'refill' => ['policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '1 minute']],
                'slow' => ['policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '1 minute', 'amount' => 1]],
                'login' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes', 'cache_pool' => 'cache.login', 'lock_factory' => 'lock.login'],
                'forms' => ['policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '10 minutes', 'amount' => 2], 'storage' => 'app.storage'],
                'monthly' => ['policy' => 'fixed_window', 'limit' => 1000, 'interval' => '1 month', 'anchor_at' => '2026-01-01'],
                'open' => ['policy' => 'no_limit', 'observe' => true],
                'framework' => ['service' => 'limiter.anonymous'],
                'strict' => ['policy' => 'compound', 'limiters' => ['api', 'framework']],
            ],
            'forms' => ['contact' => 'forms', 'search' => [['limiter' => 'api', 'amount' => 1, 'key' => 'ip']], 'sign-up' => 'my-api'],
        ]);

        self::assertSame(['id' => 'phil_rate_limit.my-api', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 second'], $container->getDefinition('phil_rate_limit.factory.my-api')->getArgument(0));
        self::assertSame(1, $container->getDefinition('phil_rate_limit.limiter.my-api')->getArgument(1));
        self::assertSame(['id' => 'phil_rate_limit.refill', 'policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '1 minute', 'amount' => 1]], $container->getDefinition('phil_rate_limit.factory.refill')->getArgument(0));
        self::assertSame(['id' => 'phil_rate_limit.slow', 'policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '1 minute', 'amount' => 1]], $container->getDefinition('phil_rate_limit.factory.slow')->getArgument(0));

        self::assertEquals([HeaderStyle::Both], $container->getDefinition(RateLimitHeaders::class)->getArguments());
        self::assertSame('errors/429.html.twig', $container->getDefinition(TooManyRequestsResponder::class)->getArgument(1));
        self::assertEquals(['pepper', new Reference(RateLimitServices::NETWORK_ADDRESS)], $container->getDefinition(RateLimitServices::IP_KEY)->getArguments());
        self::assertSame('pepper', $container->getDefinition(RateLimitServices::IP_ROUTE_KEY)->getArgument(1));
        self::assertSame('pepper', $container->getDefinition(RateLimitServices::USER_KEY)->getArgument(1));
        self::assertSame(NetworkAddress::class, $container->getDefinition(RateLimitServices::NETWORK_ADDRESS)->getClass());
        self::assertEquals([new Definition(Truncation::class, [24, 64])], $container->getDefinition(RateLimitServices::NETWORK_ADDRESS)->getArguments());

        self::assertSame(RateLimiterFactory::class, $container->getDefinition('phil_rate_limit.factory.api')->getClass());
        self::assertEquals([['id' => 'phil_rate_limit.api', 'policy' => 'sliding_window', 'limit' => 100, 'interval' => '1 minute'], new Reference('phil_rate_limit.storage.api'), new Reference('lock.factory')], $container->getDefinition('phil_rate_limit.factory.api')->getArguments());
        self::assertSame(CacheStorage::class, $container->getDefinition('phil_rate_limit.storage.api')->getClass());
        self::assertEquals([new Reference('cache.limits')], $container->getDefinition('phil_rate_limit.storage.api')->getArguments());
        self::assertEquals([new Reference('cache.login')], $container->getDefinition('phil_rate_limit.storage.login')->getArguments());
        self::assertEquals(new Reference('lock.login'), $container->getDefinition('phil_rate_limit.factory.login')->getArgument(2));
        self::assertEquals([['id' => 'phil_rate_limit.forms', 'policy' => 'token_bucket', 'limit' => 3, 'rate' => ['interval' => '10 minutes', 'amount' => 2]], new Reference('app.storage'), new Reference('lock.factory')], $container->getDefinition('phil_rate_limit.factory.forms')->getArguments());
        self::assertFalse($container->hasDefinition('phil_rate_limit.storage.forms'));
        self::assertSame(['id' => 'phil_rate_limit.monthly', 'policy' => 'fixed_window', 'limit' => 1000, 'interval' => '1 month', 'anchor_at' => '2026-01-01'], $container->getDefinition('phil_rate_limit.factory.monthly')->getArgument(0));
        self::assertSame(['id' => 'phil_rate_limit.open', 'policy' => 'no_limit'], $container->getDefinition('phil_rate_limit.factory.open')->getArgument(0));
        self::assertFalse($container->hasDefinition('phil_rate_limit.factory.framework'));
        self::assertSame(CompoundRateLimiterFactory::class, $container->getDefinition('phil_rate_limit.factory.strict')->getClass());
        self::assertEquals([[new Reference('phil_rate_limit.factory.api'), new Reference('limiter.anonymous')]], $container->getDefinition('phil_rate_limit.factory.strict')->getArguments());

        $limiter = $container->getDefinition('phil_rate_limit.limiter.api');
        self::assertSame(SymfonyLimiter::class, $limiter->getClass());
        self::assertEquals([new Reference('phil_rate_limit.factory.api'), 60], $limiter->getArguments());
        self::assertSame([RateLimitServices::LIMITER_TAG => [['name' => 'api', 'key' => 'user_or_ip', 'amount' => 2, 'observe' => false]]], $limiter->getTags());
        self::assertSame(900, $container->getDefinition('phil_rate_limit.limiter.login')->getArgument(1));
        self::assertNull($container->getDefinition('phil_rate_limit.limiter.forms')->getArgument(1));
        self::assertNull($container->getDefinition('phil_rate_limit.limiter.strict')->getArgument(1));
        self::assertEquals(new Reference('limiter.anonymous'), $container->getDefinition('phil_rate_limit.limiter.framework')->getArgument(0));
        self::assertSame([RateLimitServices::LIMITER_TAG => [['name' => 'open', 'key' => 'ip', 'amount' => 1, 'observe' => true]]], $container->getDefinition('phil_rate_limit.limiter.open')->getTags());

        self::assertSame([
            'cache.limits' => 'phil_rate_limit.limiters.api (its cache pool)',
            'lock.factory' => 'phil_rate_limit.limiters.api (its lock factory)',
            'cache.login' => 'phil_rate_limit.limiters.login (its cache pool)',
            'lock.login' => 'phil_rate_limit.limiters.login (its lock factory)',
            'app.storage' => 'phil_rate_limit.limiters.forms.storage',
            'limiter.anonymous' => 'phil_rate_limit.limiters.framework.service',
        ], $container->getParameter(RateLimitServices::REQUIRED_PARAMETER));

        $forms = ['contact' => [['limiter' => 'forms', 'amount' => null, 'key' => null]], 'search' => [['limiter' => 'api', 'amount' => 1, 'key' => 'ip']], 'sign-up' => [['limiter' => 'my-api', 'amount' => null, 'key' => null]]];
        self::assertSame($forms, $container->getParameter(RateLimitServices::FORMS_PARAMETER));
        self::assertSame($forms, $container->getDefinition(RateLimitTypeExtension::class)->getArgument(2));
    }

    public function testTheNetworkDefaultsAndBounds(): void
    {
        self::assertEquals([new Definition(Truncation::class, [32, 64])], $this->load(['network' => true])->getDefinition(RateLimitServices::NETWORK_ADDRESS)->getArguments());
        self::assertEquals([new Definition(Truncation::class, [0, 0])], $this->load(['network' => ['ipv4_prefix' => 0, 'ipv6_prefix' => 0]])->getDefinition(RateLimitServices::NETWORK_ADDRESS)->getArguments());
        self::assertEquals([new Definition(Truncation::class, [32, 128])], $this->load(['network' => ['ipv6_prefix' => 128]])->getDefinition(RateLimitServices::NETWORK_ADDRESS)->getArguments());
        self::assertFalse($this->load([])->hasDefinition(RateLimitServices::NETWORK_ADDRESS));
    }

    public function testBadConfigurationsFailTheBuild(): void
    {
        $cases = [
            'phil_rate_limit.limiters.a b: a limiter\'s name is 1 to 64 letters, digits, "_", "." and "-".' => ['limiters' => ['a b' => ['policy' => 'no_limit']]],
            'phil_rate_limit.limiters.api: a limiter has either a policy or a service.' => ['limiters' => ['api' => []]],
            'phil_rate_limit.limiters.both: a limiter has either a policy or a service.' => ['limiters' => ['both' => ['policy' => 'no_limit', 'service' => 'x']]],
            'phil_rate_limit.limiters.api: one request cannot consume 4 of a limit of 3.' => ['limiters' => ['api' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 minute', 'amount' => 4]]],
            'phil_rate_limit.limiters.api: the policy "fixed_window" needs a limit.' => ['limiters' => ['api' => ['policy' => 'fixed_window', 'interval' => '1 minute']]],
            'phil_rate_limit.limiters.api: Cannot parse interval "often"' => ['limiters' => ['api' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => 'often']]],
            'phil_rate_limit.limiters.api: The "anchor_at" option is only supported with the "fixed_window" policy.' => ['limiters' => ['api' => ['policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 minute', 'anchor_at' => '2026-01-01']]],
            'phil_rate_limit.limiters.all: a compound limiter names the limiters it counts.' => ['limiters' => ['all' => ['policy' => 'compound']]],
            'phil_rate_limit.limiters.all: "nope" is not a limiter of a policy or a service declared beside it.' => ['limiters' => ['all' => ['policy' => 'compound', 'limiters' => ['nope']]]],
            'phil_rate_limit.limiters.all: "inner" is not a limiter of a policy or a service declared beside it.' => ['limiters' => ['inner' => ['policy' => 'compound', 'limiters' => ['x']], 'all' => ['policy' => 'compound', 'limiters' => ['inner']], 'x' => ['policy' => 'no_limit']]],
            "phil_rate_limit.forms.contact: A rate limit is a limiter's name" => ['forms' => ['contact' => 3]],
            'The value "loud" is not allowed for path "phil_rate_limit.headers"' => ['headers' => 'loud'],
            'Should be less than or equal to 128' => ['network' => ['ipv6_prefix' => 129]],
            'Should be less than or equal to 32' => ['network' => ['ipv4_prefix' => 33]],
            'Should be greater than or equal to 0' => ['network' => ['ipv4_prefix' => -1]],
            'path "phil_rate_limit.network.ipv6_prefix". Should be greater than or equal to 0' => ['network' => ['ipv6_prefix' => -1]],
            'path "phil_rate_limit.limiters.api.limit". Should be greater than or equal to 1' => ['limiters' => ['api' => ['policy' => 'fixed_window', 'limit' => 0, 'interval' => '1 minute']]],
            'path "phil_rate_limit.limiters.api.rate.amount". Should be greater than or equal to 1' => ['limiters' => ['api' => ['policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '1 minute', 'amount' => 0]]]],
            'Should be greater than or equal to 1' => ['limiters' => ['api' => ['policy' => 'no_limit', 'amount' => 0]]],
            'The path "phil_rate_limit.limiters.api.key" cannot contain an empty value' => ['limiters' => ['api' => ['policy' => 'no_limit', 'key' => '']]],
        ];

        foreach ($cases as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
                self::assertSame(0, $invalidConfigurationException->getCode());
            }
        }
    }

    public function testAnOptionTheFactoryRefusesKeepsItsCause(): void
    {
        try {
            $this->load(['limiters' => ['api' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => 'often']]]);
            self::fail('An interval the factory cannot read is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertInstanceOf(LogicException::class, $invalidConfigurationException->getPrevious());
        }

        try {
            $this->load(['forms' => ['contact' => 3]]);
            self::fail('A form rule of another shape is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertInstanceOf(InvalidArgumentException::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testTheNames(): void
    {
        self::assertSame('phil_rate_limit.limiter', RateLimitServices::LIMITER_TAG);
        self::assertSame('phil_rate_limit.key_resolver', RateLimitServices::KEY_TAG);
        self::assertSame('@PhilRateLimit/too_many_requests.html.twig', PhilRateLimitBundle::TEMPLATE);
    }

    public function testTheStoresOfTheLimitersAreWatched(): void
    {
        $container = $this->load(['limiters' => [
            'pooled' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
            'own' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute', 'cache_pool' => 'cache.own'],
            'stored' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute', 'storage' => 'app.storage'],
            'free' => ['policy' => 'no_limit', 'cache_pool' => 'cache.free'],
        ]]);

        self::assertSame([
            'phil_rate_limit.limiters.pooled.cache_pool' => ['store' => 'cache.app', 'switch' => null],
            'phil_rate_limit.limiters.own.cache_pool' => ['store' => 'cache.own', 'switch' => null],
            'phil_rate_limit.limiters.stored.storage' => ['store' => 'app.storage', 'switch' => null],
        ], RateLimitGuard::watched($container));
    }

    public function testASingleServerIsNotHeldToASharedStore(): void
    {
        $container = $this->load(['single_server' => true, 'limiters' => ['pooled' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute']]]);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    public function testTheBundleGuardsItsStores(): void
    {
        $container = new ContainerBuilder();
        new PhilRateLimitBundle()->build($container);

        self::assertTrue(array_any($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
        self::assertTrue(array_any($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof LimitersPass));
    }

    public function testTheWarmerIsAlwaysThere(): void
    {
        $warmer = $this->load([])->getDefinition(RateLimitCacheWarmer::class);

        self::assertEquals([new Reference(Limiters::class), new Reference(KeyResolvers::class), new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), []], $warmer->getArguments());
        self::assertTrue($warmer->hasTag('kernel.cache_warmer'));
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
        $extension = new PhilRateLimitBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
