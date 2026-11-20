<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit;

use Phil\CircuitBreakerBundle\Infrastructure\DependencyInjection\SharedStateGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(SharedStateGuard::class)]
final class SharedStateGuardTest extends TestCase
{
    #[TestWith([FilesystemAdapter::class])]
    #[TestWith([ApcuAdapter::class])]
    #[TestWith([ArrayAdapter::class])]
    public function testAPoolLocalToEachServerIsRefusedInProduction(string $class): void
    {
        $container = $this->container('prod', 'cache.local');
        $container->register('cache.local', $class);

        try {
            new SharedStateGuard()->process($container);
            self::fail('A local pool was accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_circuit_breaker.cache_pool: the pool "cache.local" is local to each server, so each server would open its circuits on its own failures. Name a cache pool on a shared store — Redis, Memcached, a database —, a "state_store" of the application, or set "single_server" to true on a single server.', $invalidConfigurationException->getMessage());
        }
    }

    public function testThePoolOfTheFrameworkOnTheFilesystemIsLocal(): void
    {
        $container = $this->container('prod', 'cache.app');
        $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.filesystem'));

        $this->expectException(InvalidConfigurationException::class);

        new SharedStateGuard()->process($container);
    }

    public function testASharedPoolPasses(): void
    {
        $container = $this->container('prod', 'cache.redis');
        $container->register('cache.redis', RedisAdapter::class);
        new SharedStateGuard()->process($container);

        self::assertFalse($container->hasParameter(SharedStateGuard::POOL_PARAMETER));
    }

    public function testOutsideProductionAnyPoolPasses(): void
    {
        $container = $this->container('dev', 'cache.local');
        $container->register('cache.local', FilesystemAdapter::class);
        new SharedStateGuard()->process($container);

        self::assertFalse($container->hasParameter(SharedStateGuard::POOL_PARAMETER));
    }

    public function testAParameterThatIsNoServiceIdIsDropped(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter(SharedStateGuard::POOL_PARAMETER, ['cache.local']);
        $container->register('cache.local', FilesystemAdapter::class);
        new SharedStateGuard()->process($container);

        self::assertFalse($container->hasParameter(SharedStateGuard::POOL_PARAMETER));
    }

    public function testWithoutAPoolToCheckNothingIsAsked(): void
    {
        $container = new ContainerBuilder();
        new SharedStateGuard()->process($container);

        self::assertFalse($container->hasParameter('kernel.environment'));
    }

    private function container(string $environment, string $pool): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        $container->setParameter(SharedStateGuard::POOL_PARAMETER, $pool);

        return $container;
    }
}
