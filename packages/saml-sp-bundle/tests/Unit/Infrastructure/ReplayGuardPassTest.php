<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Infrastructure;

use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSpBundle\Infrastructure\Console\PurgeAssertionsCommand;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\PoolLocality;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\ReplayGuardPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(ReplayGuardPass::class)]
#[CoversClass(PoolLocality::class)]
final class ReplayGuardPassTest extends TestCase
{
    public function testWithoutItsParameterThePassDoesNothing(): void
    {
        $container = $this->container('prod');
        new ReplayGuardPass()->process($container);

        self::assertFalse($container->has(ReplayGuard::class));
    }

    public function testTheGuardOfTheApplicationIsKept(): void
    {
        $container = $this->container('prod', []);
        $container->register('doctrine.dbal.default_connection');
        $container->register('app.guard');
        $container->setAlias(ReplayGuard::class, 'app.guard');
        new ReplayGuardPass()->process($container);

        self::assertSame('app.guard', (string) $container->getAlias(ReplayGuard::class));
        self::assertFalse($container->has(DbalReplayGuard::class));
        self::assertFalse($container->has(CacheReplayGuard::class));
        self::assertFalse($container->hasParameter(ReplayGuardPass::PARAMETER));
    }

    public function testTheConnectionIsTheDefaultWhenItExists(): void
    {
        $container = $this->container('prod', ['table' => 'acme_used_assertion', 'clock' => 'app.clock']);
        $container->register('doctrine.dbal.default_connection');
        new ReplayGuardPass()->process($container);

        self::assertSame(DbalReplayGuard::class, (string) $container->getAlias(ReplayGuard::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference('app.clock'), 'acme_used_assertion'], $container->getDefinition(DbalReplayGuard::class)->getArguments());
        self::assertFalse($container->has(CacheReplayGuard::class));
        self::assertFalse($container->hasParameter(ReplayGuardPass::PARAMETER));
        self::assertEquals([new Reference(DbalReplayGuard::class), new Reference('app.clock')], $container->getDefinition(PurgeAssertionsCommand::class)->getArguments());
        self::assertSame([['command' => 'phil:saml:purge-assertions']], $container->getDefinition(PurgeAssertionsCommand::class)->getTag('console.command'));
    }

    public function testTheConnectionIsNamed(): void
    {
        $container = $this->container('prod', ['store' => 'doctrine', 'connection' => 'audit']);
        $container->register('doctrine.dbal.default_connection');
        $container->register('doctrine.dbal.audit_connection');
        new ReplayGuardPass()->process($container);

        self::assertEquals(new Reference('doctrine.dbal.audit_connection'), $container->getDefinition(DbalReplayGuard::class)->getArgument(0));
    }

    public function testWithoutAConnectionTheCachePoolRemembers(): void
    {
        $container = $this->container('dev', []);
        new ReplayGuardPass()->process($container);

        self::assertSame(CacheReplayGuard::class, (string) $container->getAlias(ReplayGuard::class));
        self::assertEquals([new Reference('cache.app')], $container->getDefinition(CacheReplayGuard::class)->getArguments());
        self::assertFalse($container->has(DbalReplayGuard::class));
        self::assertFalse($container->has(PurgeAssertionsCommand::class));
        self::assertFalse($container->hasParameter(ReplayGuardPass::PARAMETER));
    }

    public function testTheCacheStoreIgnoresTheConnection(): void
    {
        $container = $this->container('dev', ['store' => 'cache']);
        $container->register('doctrine.dbal.default_connection');
        new ReplayGuardPass()->process($container);

        self::assertSame(CacheReplayGuard::class, (string) $container->getAlias(ReplayGuard::class));
    }

    public function testTheDoctrineStoreNeedsItsConnection(): void
    {
        $container = $this->container('dev', ['store' => 'doctrine', 'connection' => 'audit']);
        $container->register('doctrine.dbal.default_connection');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_saml_sp.replay_guard.store: there is no Doctrine DBAL connection "audit" (the service "doctrine.dbal.audit_connection"). Configure it with doctrine/doctrine-bundle, name another one in "replay_guard.connection", or set "replay_guard.store" to "cache".');

        new ReplayGuardPass()->process($container);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function localAdapters(): iterable
    {
        foreach (PoolLocality::LOCAL_ADAPTERS as $adapter) {
            yield $adapter => [$adapter];
        }
    }

    #[DataProvider('localAdapters')]
    public function testALocalPoolIsRefusedInProduction(string $adapter): void
    {
        $container = $this->container('prod', []);
        $container->setDefinition('cache.app', new ChildDefinition($adapter));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_saml_sp.cache_pool: the pool "cache.app" keeps the accepted assertions on each server, so an assertion posted again to another server would be accepted again. Configure a Doctrine DBAL connection for "replay_guard", name a cache pool on a shared store — Redis, Memcached, a database —, or set "single_server" to true on a single server.');

        new ReplayGuardPass()->process($container);
    }

    public function testALocalPoolIsAcceptedOnASingleServerOrOutOfProduction(): void
    {
        foreach ([['prod', true], ['dev', false], ['test', false]] as [$environment, $single]) {
            $container = $this->container($environment, ['single_server' => $single]);
            $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.filesystem'));
            new ReplayGuardPass()->process($container);

            self::assertSame(CacheReplayGuard::class, (string) $container->getAlias(ReplayGuard::class), $environment);
        }
    }

    public function testASharedPoolIsAcceptedInProduction(): void
    {
        $container = $this->container('prod', ['pool' => 'cache.shared']);
        $container->setDefinition('cache.shared', new ChildDefinition('cache.adapter.redis'));
        new ReplayGuardPass()->process($container);

        self::assertEquals([new Reference('cache.shared')], $container->getDefinition(CacheReplayGuard::class)->getArguments());
    }

    public function testTheLocalityFollowsParentsAliasesAndClasses(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('local.class', ArrayAdapter::class);
        $container->setDefinition('pool.parent', new ChildDefinition('cache.adapter.apcu'));
        $container->setDefinition('pool.child', new ChildDefinition('pool.parent'));
        $container->setAlias('pool.alias', 'pool.child');
        $container->register('pool.by_class', '%local.class%');
        $container->register('pool.by_rooted_class', '\\'.ArrayAdapter::class);
        $container->register('pool.redis', RedisAdapter::class);
        $container->register('pool.no_class');
        $container->setDefinition('pool.unknown_parent', new ChildDefinition('nowhere'));
        $container->setDefinition('pool.loop.a', new ChildDefinition('pool.loop.b'));
        $container->setDefinition('pool.loop.b', new ChildDefinition('pool.loop.a'));

        $locality = new PoolLocality();

        foreach (PoolLocality::LOCAL_CLASSES as $index => $class) {
            $container->register('pool.local.'.$index, $class);
            self::assertTrue($locality->isLocal($container, 'pool.local.'.$index), $class);
        }

        self::assertTrue($locality->isLocal($container, 'pool.parent'));
        self::assertTrue($locality->isLocal($container, 'pool.child'));
        self::assertTrue($locality->isLocal($container, 'pool.alias'));
        self::assertTrue($locality->isLocal($container, 'pool.by_class'));
        self::assertTrue($locality->isLocal($container, 'pool.by_rooted_class'));
        self::assertFalse($locality->isLocal($container, 'pool.redis'));
        self::assertFalse($locality->isLocal($container, 'pool.no_class'));
        self::assertFalse($locality->isLocal($container, 'pool.unknown_parent'));
        self::assertFalse($locality->isLocal($container, 'pool.loop.a'));
        self::assertFalse($locality->isLocal($container, 'pool.missing'));
    }

    public function testTheLocalityStopsAfterThirtyTwoServices(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('pool.0', new ChildDefinition('cache.adapter.array'));

        for ($i = 1; $i <= 32; ++$i) {
            $container->setDefinition('pool.'.$i, new ChildDefinition('pool.'.($i - 1)));
        }

        $locality = new PoolLocality();

        self::assertTrue($locality->isLocal($container, 'pool.31'));
        self::assertFalse($locality->isLocal($container, 'pool.32'));
    }

    /**
     * @param array<string, mixed> $choice
     */
    private function container(string $environment, ?array $choice = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);
        $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.filesystem'));

        if (null !== $choice) {
            $container->setParameter(ReplayGuardPass::PARAMETER, array_replace(['store' => 'auto', 'connection' => 'default', 'table' => DbalReplayGuard::TABLE, 'pool' => 'cache.app', 'clock' => 'clock', 'single_server' => false], $choice));
        }

        return $container;
    }
}
