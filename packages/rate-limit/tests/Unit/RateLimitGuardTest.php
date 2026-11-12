<?php

declare(strict_types=1);

namespace Phil\RateLimit\Tests\Unit;

use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimit\StoreLocality;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(RateLimitGuard::class)]
#[UsesClass(StoreLocality::class)]
final class RateLimitGuardTest extends TestCase
{
    public function testAMissingComponentIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('the.path needs symfony/rate-limiter: install it, or set "enabled" to false.');

        RateLimitGuard::requireComponent(false, 'the.path');
    }

    #[DoesNotPerformAssertions]
    public function testAnInstalledComponentPasses(): void
    {
        RateLimitGuard::requireComponent(true, 'the.path');
    }

    public function testWatchedStoresAddUpByPath(): void
    {
        $container = new ContainerBuilder();
        self::assertSame([], RateLimitGuard::watched($container));

        RateLimitGuard::watch($container, 'cache.one', 'path.one');
        RateLimitGuard::watch($container, 'cache.one', 'path.two', false, 'enabled');

        self::assertSame([
            'path.one' => ['store' => 'cache.one', 'switch' => null],
            'path.two' => ['store' => 'cache.one', 'switch' => 'enabled'],
        ], $container->getParameter(RateLimitGuard::STORES_PARAMETER));
        self::assertSame($container->getParameter(RateLimitGuard::STORES_PARAMETER), RateLimitGuard::watched($container));
    }

    public function testASingleServerIsNotWatched(): void
    {
        $container = new ContainerBuilder();
        RateLimitGuard::watch($container, 'cache.one', 'path.one', true);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    public function testALocalStoreIsRefusedInProduction(): void
    {
        $container = $this->container('prod');
        $container->setDefinition('cache.pool', new Definition(FilesystemAdapter::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('the.path: the store "cache.pool" is local to each server, so a client could spend the limit once per server. Name a cache pool on a shared store — Redis, Memcached, a database —, or set "single_server" to true on a single server.');

        new RateLimitGuard()->process($container);
    }

    public function testTheSwitchIsOffered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('cache.pool', new Definition(FilesystemAdapter::class));
        RateLimitGuard::watch($container, 'cache.pool', 'the.path', false, 'enabled');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Name a cache pool on a shared store — Redis, Memcached, a database —, set "single_server" to true on a single server, or set "enabled" to false.');

        new RateLimitGuard()->process($container);
    }

    public function testASharedStorePassesAndTheParameterIsRemoved(): void
    {
        $container = $this->container('prod');
        $container->setDefinition('cache.pool', new Definition(RedisAdapter::class));

        new RateLimitGuard()->process($container);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    public function testEachStoreIsChecked(): void
    {
        $container = $this->container('prod');
        $container->setDefinition('cache.pool', new Definition(RedisAdapter::class));
        $container->setDefinition('cache.local', new Definition(FilesystemAdapter::class));
        RateLimitGuard::watch($container, 'cache.local', 'second.path');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('second.path: the store "cache.local"');

        new RateLimitGuard()->process($container);
    }

    public function testASecondPassFindsNothingLeft(): void
    {
        $container = $this->container('dev');
        new RateLimitGuard()->process($container);
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('cache.pool', new Definition(FilesystemAdapter::class));

        new RateLimitGuard()->process($container);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    public function testALocalStorePassesOutsideProduction(): void
    {
        $container = $this->container('dev');
        $container->setDefinition('cache.pool', new Definition(FilesystemAdapter::class));

        new RateLimitGuard()->process($container);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    public function testNothingWatchedNothingChecked(): void
    {
        $container = new ContainerBuilder();

        new RateLimitGuard()->process($container);

        self::assertFalse($container->hasParameter('kernel.environment'));
    }

    private function container(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        RateLimitGuard::watch($container, 'cache.pool', 'the.path');

        return $container;
    }
}
