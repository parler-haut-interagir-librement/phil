<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Unit;

use InvalidArgumentException;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Dbal\UsageSchemaListener;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\LimitChain;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Subscription\FeatureLimits;
use Phil\UsageMeter\UsageMeter;
use Phil\UsageMeter\UsageStore;
use Phil\UsageMeterBundle\Infrastructure\Configuration\ConfiguredMeters;
use Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand;
use Phil\UsageMeterBundle\Infrastructure\DependencyInjection\UsageMeterServices;
use Phil\UsageMeterBundle\Infrastructure\RateLimit\MeterLimiter;
use Phil\UsageMeterBundle\PhilUsageMeterBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilUsageMeterBundle::class)]
#[CoversClass(UsageMeterServices::class)]
final class PhilUsageMeterBundleTest extends TestCase
{
    public function testTheDefaultsKeepTheCountersInTheDatabase(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_usage_meter', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', Meters::class, DbalUsageStore::class, UsageSchemaListener::class, UsageMeter::class, PurgeUsageCommand::class],
            array_keys($container->getDefinitions()),
        );
        self::assertSame([ConfiguredMeters::class, 'fromConfiguration'], $container->getDefinition(Meters::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(Meters::class)->getArguments());
        self::assertSame(DbalUsageStore::class, (string) $container->getAlias(UsageStore::class));

        $connection = new Reference('doctrine.dbal.default_connection');
        self::assertEquals([$connection, 'phil_usage_counter', 'phil_usage_key'], $container->getDefinition(DbalUsageStore::class)->getArguments());
        self::assertEquals([$connection, new Reference(DbalUsageStore::class)], $container->getDefinition(UsageSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(UsageSchemaListener::class)->getTags());

        $meter = $container->getDefinition(UsageMeter::class);
        self::assertEquals([new Reference(Meters::class), new Reference(UsageStore::class), new Reference('clock'), null, null], $meter->getArguments());
        self::assertTrue($meter->isPublic());

        self::assertEquals([new Reference(UsageMeter::class)], $container->getDefinition(PurgeUsageCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(PurgeUsageCommand::class)->getTags());

        self::assertSame('phil_usage_meter.limits', UsageMeterServices::LIMITS);
        self::assertSame('phil_usage_meter.limits.subscription', UsageMeterServices::SUBSCRIPTION_LIMITS);
        self::assertSame('phil_usage_meter.rate_limiter.', UsageMeterServices::RATE_LIMITER_PREFIX);
        self::assertSame('phil_rate_limit.limiter', UsageMeterServices::RATE_LIMITER_TAG);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $meters = [
            'exports' => ['limit' => 3, 'calendar' => 'month', 'timezone' => 'America/Toronto'],
            'requests' => ['limit' => 600, 'rolling' => 3600, 'buckets' => 60],
            'ai-tokens' => ['limit' => 'unlimited', 'billing' => 1767225600, 'months' => 12],
        ];
        $container = $this->load([
            'dbal' => ['connection' => 'meters', 'counter_table' => 'quota_counter', 'key_table' => 'quota_key'],
            'clock' => 'app.clock',
            'limit_providers' => ['app.overrides', 'app.plans'],
            'subscription_features' => ['prefix' => 'quota:'],
            'anchor_provider' => 'app.anchors',
            'meters' => $meters,
            'rate_limiters' => [
                'api' => ['meter' => 'requests'],
                'paying-members' => ['meter' => 'requests', 'key' => 'user', 'amount' => 2],
                'accounts' => ['meter' => 'exports', 'key' => 'app.accounts', 'idempotency_key' => true],
                '42' => ['meter' => 'exports'],
            ],
            'idempotency_secret' => 'pepper',
        ]);

        self::assertSame([[
            'exports' => ['limit' => 3, 'calendar' => 'month', 'timezone' => 'America/Toronto', 'rolling' => null, 'buckets' => 1, 'billing' => null, 'months' => 1],
            'requests' => ['limit' => 600, 'rolling' => 3600, 'buckets' => 60, 'calendar' => null, 'timezone' => 'UTC', 'billing' => null, 'months' => 1],
            'ai-tokens' => ['limit' => 'unlimited', 'billing' => '@1767225600', 'months' => 12, 'calendar' => null, 'timezone' => 'UTC', 'rolling' => null, 'buckets' => 1],
        ]], $container->getDefinition(Meters::class)->getArguments());

        $connection = new Reference('doctrine.dbal.meters_connection');
        self::assertEquals([$connection, 'quota_counter', 'quota_key'], $container->getDefinition(DbalUsageStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'meters']]], $container->getDefinition(UsageSchemaListener::class)->getTags());

        $subscription = $container->getDefinition(UsageMeterServices::SUBSCRIPTION_LIMITS);
        self::assertSame(FeatureLimits::class, $subscription->getClass());
        self::assertSame([FeatureLimits::class, 'fromSubscriptions'], $subscription->getFactory());
        self::assertEquals([new Reference(FeatureAccess::class), 'quota:'], $subscription->getArguments());

        $limits = $container->getDefinition(UsageMeterServices::LIMITS);
        self::assertSame(LimitChain::class, $limits->getClass());
        self::assertEquals([new Reference('app.overrides'), new Reference('app.plans'), new Reference(UsageMeterServices::SUBSCRIPTION_LIMITS)], $limits->getArguments());

        self::assertEquals(
            [new Reference(Meters::class), new Reference(UsageStore::class), new Reference('app.clock'), new Reference(UsageMeterServices::LIMITS), new Reference('app.anchors')],
            $container->getDefinition(UsageMeter::class)->getArguments(),
        );

        $limiters = [
            'api' => [['requests', false, 'ip', 1]],
            'paying-members' => [['requests', false, 'user', 2]],
            'accounts' => [['exports', true, 'app.accounts', 1]],
            '42' => [['exports', false, 'ip', 1]],
        ];

        foreach ($limiters as $name => [[$meter, $keyed, $key, $amount]]) {
            $limiter = $container->getDefinition(UsageMeterServices::RATE_LIMITER_PREFIX.$name);
            self::assertSame(MeterLimiter::class, $limiter->getClass());
            self::assertEquals([new Reference(UsageMeter::class), new Reference('app.clock'), (string) $name, $meter, $keyed, 'pepper'], $limiter->getArguments());
            self::assertSame((string) $name, $limiter->getArgument(2));
            self::assertSame([UsageMeterServices::RATE_LIMITER_TAG => [['name' => (string) $name, 'key' => $key, 'amount' => $amount]]], $limiter->getTags());
        }

        self::assertSame('%kernel.secret%', $this->load(['meters' => $meters, 'rate_limiters' => ['api' => ['meter' => 'requests']]])->getDefinition(UsageMeterServices::RATE_LIMITER_PREFIX.'api')->getArgument(5));
    }

    public function testTheRateLimitersNeedTheRateLimitBundle(): void
    {
        $config = ['meters' => ['requests' => ['limit' => 1, 'calendar' => 'day']], 'rate_limiters' => ['api' => ['meter' => 'requests']]];

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['PhilRateLimitBundle' => PhilRateLimitBundle::class]);
        $this->extension()->load([$config], $container);
        self::assertTrue($container->hasDefinition(UsageMeterServices::RATE_LIMITER_PREFIX.'api'));

        $alone = new ContainerBuilder();
        $alone->setParameter('kernel.bundles', []);
        $this->extension()->load([['meters' => $config['meters']]], $alone);
        self::assertFalse($alone->hasDefinition(UsageMeterServices::RATE_LIMITER_PREFIX.'api'));

        $this->expectExceptionObject(new InvalidConfigurationException('phil_usage_meter.rate_limiters are limiters of phil/rate-limit-bundle: register PhilRateLimitBundle in config/bundles.php.'));
        $this->extension()->load([$config], $alone);
    }

    public function testTheLimitProvidersAloneMakeTheChain(): void
    {
        $container = $this->load(['limit_providers' => ['app.plans']]);

        self::assertFalse($container->hasDefinition(UsageMeterServices::SUBSCRIPTION_LIMITS));
        self::assertEquals([new Reference('app.plans')], $container->getDefinition(UsageMeterServices::LIMITS)->getArguments());
        self::assertContainsOnlyInstancesOf(Reference::class, $container->getDefinition(UsageMeterServices::LIMITS)->getArguments());

        $container = $this->load(['subscription_features' => true]);

        self::assertEquals([new Reference(FeatureAccess::class), 'usage:'], $container->getDefinition(UsageMeterServices::SUBSCRIPTION_LIMITS)->getArguments());
        self::assertEquals([new Reference(UsageMeterServices::SUBSCRIPTION_LIMITS)], $container->getDefinition(UsageMeterServices::LIMITS)->getArguments());
        self::assertFalse($this->load([])->hasDefinition(UsageMeterServices::LIMITS));
    }

    public function testTheOtherStores(): void
    {
        $memory = $this->load(['store' => 'memory']);

        self::assertSame(InMemoryUsageStore::class, (string) $memory->getAlias(UsageStore::class));
        self::assertSame(InMemoryUsageStore::class, $memory->getDefinition(InMemoryUsageStore::class)->getClass());
        self::assertFalse($memory->hasDefinition(DbalUsageStore::class));
        self::assertFalse($memory->hasDefinition(UsageSchemaListener::class));

        $own = $this->load(['store' => 'app.store']);

        self::assertSame('app.store', (string) $own->getAlias(UsageStore::class));
        self::assertFalse($own->hasDefinition(DbalUsageStore::class));
        self::assertFalse($own->hasDefinition(InMemoryUsageStore::class));
        self::assertSame('dbal', UsageMeterServices::STORE_DBAL);
        self::assertSame('memory', UsageMeterServices::STORE_MEMORY);
    }

    public function testBadConfigurationsFailTheBuild(): void
    {
        $cases = [
            'phil_usage_meter.meters: The meter "exports" names exactly one period' => ['meters' => ['exports' => ['limit' => 1]]],
            'phil_usage_meter.meters: The limit of the meter "exports"' => ['meters' => ['exports' => ['limit' => 'lots', 'calendar' => 'day']]],
            'phil_usage_meter.rate_limiters.api counts on the meter "requests", which the meters section does not declare.' => ['meters' => ['exports' => ['limit' => 1, 'calendar' => 'day']], 'rate_limiters' => ['api' => ['meter' => 'requests']]],
            'The value "hour" is not allowed' => ['meters' => ['exports' => ['limit' => 1, 'calendar' => 'hour']]],
            'Should be greater than or equal to 1' => ['meters' => ['exports' => ['limit' => 1, 'calendar' => 'day']], 'rate_limiters' => ['api' => ['meter' => 'exports', 'amount' => 0]]],
            'The child config "limit" under "phil_usage_meter.meters.exports" must be configured' => ['meters' => ['exports' => ['calendar' => 'day']]],
            'The path "phil_usage_meter.store" cannot contain an empty value' => ['store' => ''],
        ];

        foreach ($cases as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testABadMeterKeepsTheLibrarysExceptionAsItsCause(): void
    {
        try {
            $this->load(['meters' => ['exports' => ['limit' => 1]]]);
            self::fail('A meter without a period is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_usage_meter.meters: The meter "exports" names exactly one period: calendar, rolling or billing.', $invalidConfigurationException->getMessage());
            self::assertInstanceOf(InvalidArgumentException::class, $invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
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
        $extension = new PhilUsageMeterBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
