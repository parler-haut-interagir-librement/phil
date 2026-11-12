<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit;

use Phil\AnalyticsBundle\Application\Measure\MeasureCounters;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsConfiguration;
use Phil\AnalyticsBundle\PhilAnalyticsBundle;
use Phil\RateLimit\RateLimitGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[CoversClass(PhilAnalyticsBundle::class)]
#[CoversClass(AnalyticsConfiguration::class)]
final class PhilAnalyticsBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilAnalytics(): void
    {
        $extension = new PhilAnalyticsBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('phil_analytics', $extension->getAlias());
    }

    public function testWithoutDoctrineTheDbalStoreHasNoConnection(): void
    {
        $container = new ContainerBuilder();
        $extension = new PhilAnalyticsBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['counters' => ['connection' => null]]], $container->getExtensionConfig('phil_analytics'));
    }

    public function testWithDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = new PhilAnalyticsBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('phil_analytics'));
    }

    public function testTheSaltAndTheLimiterPoolsAreWatched(): void
    {
        self::assertSame(
            ['phil_analytics.salt.cache_pool' => ['store' => 'cache.app', 'switch' => null], 'phil_analytics.rate_limiter.cache_pool' => ['store' => 'cache.app', 'switch' => null]],
            RateLimitGuard::watched($this->loaded([])),
        );
        self::assertSame([], RateLimitGuard::watched($this->loaded(['single_server' => true])));
    }

    public function testLoadingTheExtensionRegistersTheServices(): void
    {
        self::assertTrue($this->loaded([])->hasDefinition(MeasureCounters::class));
    }

    public function testTheBuildChecksTheWatchedPools(): void
    {
        $container = new ContainerBuilder();
        new PhilAnalyticsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loaded(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = new PhilAnalyticsBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([['counters' => ['connection' => null]] + $config], $container);

        return $container;
    }
}
