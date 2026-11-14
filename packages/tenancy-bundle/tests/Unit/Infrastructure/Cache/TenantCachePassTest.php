<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCachePass;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedPool;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedTagAwarePool;
use Phil\TenancyBundle\PhilTenancyBundle;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture\TagAwareWithoutNamespaces;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Which pools the pass scopes, on a container laid out as Symfony's cache configuration lays it out.
 */
#[CoversClass(TenantCachePass::class)]
final class TenantCachePassTest extends TestCase
{
    public function testCacheAppAndThePoolsBuiltOnItAreScoped(): void
    {
        $container = $this->container();
        $this->pool($container, 'app.prices');
        $this->pool($container, 'app.reports', 'app.prices');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        foreach (['cache.app', 'app.prices', 'app.reports'] as $pool) {
            $this->assertDecorated($container, $pool, TenantScopedPool::class);
        }

        $this->assertDecorated($container, 'cache.app.taggable', TenantScopedTagAwarePool::class, false);
    }

    public function testATagAwarePoolOfTheApplicationIsScopedThroughItsItems(): void
    {
        $container = $this->container();
        // As framework.cache.pools lays out `tags: true`: the items under `.<pool>.inner`, the adapter under `<pool>`.
        $this->pool($container, '.app.catalogue.inner');
        $container->register('app.catalogue', TagAwareAdapter::class)
            ->addArgument(new Reference('.app.catalogue.inner'))
            ->addTag('cache.taggable', ['pool' => 'app.catalogue']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, '.app.catalogue.inner', TenantScopedPool::class);
        $this->assertDecorated($container, 'app.catalogue', TenantScopedTagAwarePool::class, false);
    }

    public function testANativelyTagAwarePoolIsScopedByTheTagAwareDecorator(): void
    {
        $container = $this->container();
        $container->register('cache.adapter.redis_tag_aware', RedisTagAwareAdapter::class)->setAbstract(true)->addTag('cache.pool');
        $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.redis_tag_aware'))
            ->addTag('cache.pool')
            ->addTag('cache.taggable', ['pool' => 'cache.app']);
        $container->removeDefinition('cache.app.taggable');
        $container->setAlias('cache.app.taggable', 'cache.app');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'cache.app', TenantScopedTagAwarePool::class, true);
    }

    public function testSymfonysSharedPoolsAndTheGlobalPoolsStayAsTheyAre(): void
    {
        $container = $this->container(['app.exchange_rates', 'app.catalogue']);

        foreach ([...TenantCachePass::SHARED_POOLS, 'app.exchange_rates', '.app.catalogue.inner'] as $pool) {
            $this->pool($container, $pool);
        }

        $container->register('app.catalogue', TagAwareAdapter::class)
            ->addArgument(new Reference('.app.catalogue.inner'))
            ->addTag('cache.taggable', ['pool' => 'app.catalogue']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        foreach ([...TenantCachePass::SHARED_POOLS, 'app.exchange_rates', '.app.catalogue.inner', 'app.catalogue'] as $pool) {
            self::assertFalse($container->hasDefinition($pool.TenantCachePass::DECORATOR_SUFFIX), $pool);
        }

        $this->assertDecorated($container, 'cache.app', TenantScopedPool::class);
    }

    public function testCacheAppItselfCanBeShared(): void
    {
        $container = $this->container(['cache.app']);
        $this->pool($container, 'app.prices');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        self::assertFalse($container->hasDefinition('cache.app'.TenantCachePass::DECORATOR_SUFFIX));
        self::assertFalse($container->hasDefinition('cache.app.taggable'.TenantCachePass::DECORATOR_SUFFIX), 'Over a shared pool, tags stay shared.');
        $this->assertDecorated($container, 'app.prices', TenantScopedPool::class);
    }

    public function testAPoolOnAnotherAdapterIsLeftAlone(): void
    {
        $container = $this->container();
        $container->register('cache.adapter.array', ArrayAdapter::class)->setAbstract(true)->addTag('cache.pool');
        $container->setDefinition('app.local', new ChildDefinition('cache.adapter.array'))->addTag('cache.pool');
        $container->register('app.direct', NullAdapter::class)->addTag('cache.pool');
        $container->register('app.taggable_elsewhere', TagAwareAdapter::class)
            ->addArgument(new Reference('app.local'))
            ->addTag('cache.taggable', ['pool' => 'app.local']);
        $container->register('app.not_an_adapter', stdClass::class)
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        foreach (['cache.adapter.array', 'cache.adapter.filesystem', 'app.local', 'app.direct', 'app.taggable_elsewhere', 'app.not_an_adapter'] as $pool) {
            self::assertFalse($container->hasDefinition($pool.TenantCachePass::DECORATOR_SUFFIX), $pool);
        }
    }

    public function testAParentLoopIsNotFollowedForever(): void
    {
        $container = $this->container();
        $container->setDefinition('app.loop_a', new ChildDefinition('app.loop_b'))->addTag('cache.pool');
        $container->setDefinition('app.loop_b', new ChildDefinition('app.loop_a'))->addTag('cache.pool');
        $container->setDefinition('app.orphan', new ChildDefinition('app.missing'))->addTag('cache.pool');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        self::assertFalse($container->hasDefinition('app.loop_a'.TenantCachePass::DECORATOR_SUFFIX));
        self::assertFalse($container->hasDefinition('app.orphan'.TenantCachePass::DECORATOR_SUFFIX));
    }

    public function testAPoolIsTagAwareByItsClassAsWellAsByItsTag(): void
    {
        $container = $this->container();
        $container->setDefinition('app.native', new ChildDefinition('cache.app'))->setClass(RedisTagAwareAdapter::class)->addTag('cache.pool');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'app.native', TenantScopedTagAwarePool::class, true);
        $this->assertDecorated($container, 'cache.app', TenantScopedPool::class);
    }

    public function testAPoolIsTagAwareByItsTagWhenItsClassIsKnownOnlyAtRuntime(): void
    {
        $container = $this->container();
        $container->setDefinition('app.classless', new Definition())->setAbstract(true);
        $container->setDefinition('cache.app', new ChildDefinition('app.classless'))->addTag('cache.pool');
        $container->setDefinition('app.dynamic', new ChildDefinition('cache.app'))->addTag('cache.pool')->addTag('cache.taggable', ['pool' => 'app.dynamic']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'app.dynamic', TenantScopedTagAwarePool::class, true);
        $this->assertDecorated($container, 'cache.app', TenantScopedPool::class);
    }

    public function testATagAwarePoolThatCannotTakeASubNamespaceStopsTheCompilation(): void
    {
        $container = $this->container();
        $container->setDefinition('app.tags_only', new ChildDefinition('cache.app'))->setClass(TagAwareWithoutNamespaces::class)->addTag('cache.pool')->addTag('cache.taggable', ['pool' => 'app.tags_only']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The cache pool "app.tags_only" ('.TagAwareWithoutNamespaces::class.') cannot be scoped to the active tenant: it does not implement Symfony\Contracts\Cache\NamespacedPoolInterface. List it in phil_tenancy.cache.global_pools to share it between tenants.');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);
    }

    public function testAPoolInheritsItsClass(): void
    {
        $container = $this->container();
        $container->setDefinition('app.plain_adapter', new Definition(stdClass::class))->setAbstract(true);
        $container->setDefinition('cache.app', new ChildDefinition('app.plain_adapter'))->addTag('cache.pool');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The cache pool "cache.app" ('.stdClass::class.') cannot be scoped to the active tenant: it does not implement Symfony\Contracts\Cache\NamespacedPoolInterface. List it in phil_tenancy.cache.global_pools to share it between tenants.');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);
    }

    public function testAClassThatDoesNotExistLeavesTheCheckToTheContainer(): void
    {
        $container = $this->container();
        $container->setDefinition('app.later', new ChildDefinition('cache.app'))->setClass('App\\Cache\\NotYetThere')->addTag('cache.pool');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'app.later', TenantScopedPool::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesAsTheApplicationGivesThem(): iterable
    {
        yield 'a pool that merely ends as an inner pool does' => ['app.plain.inner'];
        yield 'a pool that merely starts with a dot' => ['.app.hidden'];
        yield 'the items of a tag-aware pool' => ['.app.catalogue.inner'];
    }

    #[DataProvider('namesAsTheApplicationGivesThem')]
    public function testAPoolIsNamedAsTheApplicationNamesIt(string $id): void
    {
        $container = $this->container();
        $container->setDefinition($id, new ChildDefinition('cache.app'))->setClass(stdClass::class)->addTag('cache.pool');
        $name = str_starts_with($id, '.') && str_ends_with($id, '.inner') ? substr($id, 1, -6) : $id;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The cache pool "'.$name.'" ('.stdClass::class.') cannot be scoped to the active tenant: it does not implement Symfony\Contracts\Cache\NamespacedPoolInterface. List it in phil_tenancy.cache.global_pools to share it between tenants.');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);
    }

    public function testATagAwareAdapterOverAPoolAlreadyTagAwareIsScopedOnce(): void
    {
        $container = $this->container();
        $container->setDefinition('app.both', new ChildDefinition('cache.app'))
            ->setClass(TagAwareAdapter::class)
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.pool')
            ->addTag('cache.taggable', ['pool' => 'app.both']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'app.both', TenantScopedTagAwarePool::class, true);
    }

    public function testAnAdapterLeftAloneDoesNotStopTheOnesAfterIt(): void
    {
        $container = $this->container();
        $container->removeDefinition('cache.app.taggable');
        $container->register('app.not_an_adapter', stdClass::class)
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);
        $container->register('cache.app.taggable', TagAwareAdapter::class)
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'cache.app.taggable', TenantScopedTagAwarePool::class, false);
    }

    public function testATagAwareAdapterInheritsItsClassToo(): void
    {
        $container = $this->container();
        $container->setDefinition('app.taggable_parent', new Definition(TagAwareAdapter::class))->setAbstract(true);
        $container->setDefinition('app.inherited', new ChildDefinition('app.taggable_parent'))
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        $this->assertDecorated($container, 'app.inherited', TenantScopedTagAwarePool::class, false);
    }

    public function testATagAwareAdapterWhoseParentIsMissingOrLoopsIsLeftAlone(): void
    {
        $container = $this->container();
        $container->setDefinition('app.orphan_tags', new ChildDefinition('app.missing'))
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);
        $container->setDefinition('app.loop_tags_a', new ChildDefinition('app.loop_tags_b'))
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);
        $container->setDefinition('app.loop_tags_b', new ChildDefinition('app.loop_tags_a'));

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        foreach (['app.orphan_tags', 'app.loop_tags_a'] as $adapter) {
            self::assertFalse($container->hasDefinition($adapter.TenantCachePass::DECORATOR_SUFFIX), $adapter);
        }

        $this->assertDecorated($container, 'cache.app.taggable', TenantScopedTagAwarePool::class, false);
    }

    public function testAPoolThatCannotTakeASubNamespaceStopsTheCompilation(): void
    {
        $container = $this->container();
        $container->setDefinition('app.plain', new ChildDefinition('cache.app'))->setClass(stdClass::class)->addTag('cache.pool');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The cache pool "app.plain" ('.stdClass::class.') cannot be scoped to the active tenant: it does not implement Symfony\Contracts\Cache\NamespacedPoolInterface. List it in phil_tenancy.cache.global_pools to share it between tenants.');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);
    }

    public function testAGlobalPoolThatIsNoPoolStopsTheCompilation(): void
    {
        $container = $this->container(['app.missing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The pool "app.missing" of phil_tenancy.cache.global_pools is not a cache pool.');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);
    }

    public function testNothingIsScopedWhenTheBundleLeavesTheCacheAlone(): void
    {
        $container = $this->container();
        $container->getParameterBag()->remove(TenantCachePass::GLOBAL_POOLS_PARAMETER);

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        self::assertFalse($container->hasDefinition('cache.app'.TenantCachePass::DECORATOR_SUFFIX));
    }

    public function testNothingIsScopedWithoutCacheApp(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER, []);
        $container->register('app.prices', ArrayAdapter::class)->addTag('cache.pool');

        new TenantCachePass(PhilTenancyBundle::ACTIVATION_TAG)->process($container);

        self::assertFalse($container->hasDefinition('app.prices'.TenantCachePass::DECORATOR_SUFFIX));
    }

    /**
     * `cache.app` and `cache.app.taggable` as Symfony registers them, on the filesystem adapter.
     *
     * @param list<string> $globalPools
     */
    private function container(array $globalPools = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(TenantCachePass::GLOBAL_POOLS_PARAMETER, $globalPools);
        $container->setParameter('cache.adapter.filesystem.class', FilesystemAdapter::class);
        $container->register('cache.adapter.filesystem', '%cache.adapter.filesystem.class%')->setAbstract(true)->addTag('cache.pool');
        $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.filesystem'))->setPublic(true)->addTag('cache.pool');
        $container->register('cache.app.taggable', TagAwareAdapter::class)
            ->addArgument(new Reference('cache.app'))
            ->addTag('cache.taggable', ['pool' => 'cache.app']);

        return $container;
    }

    private function pool(ContainerBuilder $container, string $id, string $parent = 'cache.app'): void
    {
        $container->setDefinition($id, new ChildDefinition($parent))->addTag('cache.pool');
    }

    /**
     * @param class-string $class
     */
    private function assertDecorated(ContainerBuilder $container, string $pool, string $class, ?bool $namespaced = null): void
    {
        $decorator = $container->getDefinition($pool.TenantCachePass::DECORATOR_SUFFIX);

        self::assertSame($class, $decorator->getClass(), $pool);
        self::assertSame([$pool, null, 0], $decorator->getDecoratedService(), $pool);
        [$inner, $context] = $decorator->getArguments();
        self::assertInstanceOf(Reference::class, $inner);
        self::assertInstanceOf(Reference::class, $context);
        self::assertSame(['.inner', TenantContext::class], [(string) $inner, (string) $context], $pool);
        self::assertSame(null === $namespaced ? [] : [$namespaced], \array_slice($decorator->getArguments(), 2), $pool);
        self::assertTrue($decorator->hasTag(PhilTenancyBundle::ACTIVATION_TAG), $pool);
        self::assertInstanceOf(Definition::class, $decorator);
    }
}
