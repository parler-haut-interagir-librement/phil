<?php

declare(strict_types=1);

namespace Phil\RateLimit\Tests\Unit;

use Phil\RateLimit\StoreLocality;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(StoreLocality::class)]
final class StoreLocalityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function localAdapters(): iterable
    {
        yield 'filesystem' => ['cache.adapter.filesystem'];
        yield 'system' => ['cache.adapter.system'];
        yield 'apcu' => ['cache.adapter.apcu'];
        yield 'array' => ['cache.adapter.array'];
    }

    #[DataProvider('localAdapters')]
    public function testAPoolOfALocalAdapterIsLocal(string $adapter): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition($adapter, new Definition(stdClass::class));
        $container->setDefinition('cache.pool', new ChildDefinition($adapter));

        self::assertTrue(new StoreLocality()->isLocal($container, 'cache.pool'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function localClasses(): iterable
    {
        yield 'apcu' => [ApcuAdapter::class];
        yield 'array' => [ArrayAdapter::class];
        yield 'filesystem' => [FilesystemAdapter::class];
        yield 'php files' => [PhpFilesAdapter::class];
        yield 'memory storage' => [InMemoryStorage::class];
        yield 'with a leading backslash' => ['\\'.FilesystemAdapter::class];
    }

    #[DataProvider('localClasses')]
    public function testALocalClassIsLocal(string $class): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('store', new Definition($class));

        self::assertTrue(new StoreLocality()->isLocal($container, 'store'));
    }

    public function testAClassFromAParameterIsResolved(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('store.class', FilesystemAdapter::class);
        $container->setDefinition('store', new Definition('%store.class%'));

        self::assertTrue(new StoreLocality()->isLocal($container, 'store'));
    }

    public function testAChainOfPoolsAndAnAliasAreFollowed(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.filesystem', new Definition(FilesystemAdapter::class));
        $container->setDefinition('cache.app', new ChildDefinition('cache.adapter.filesystem'));
        $container->setDefinition('cache.middle', new ChildDefinition('cache.app'));
        $container->setAlias('cache.pool', 'cache.middle');

        self::assertTrue(new StoreLocality()->isLocal($container, 'cache.pool'));
    }

    public function testAParentThatIsNotAnAdapterIsReadForItsClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('base', new Definition(ArrayAdapter::class));
        $container->setDefinition('cache.pool', new ChildDefinition('base'));

        self::assertTrue(new StoreLocality()->isLocal($container, 'cache.pool'));
    }

    public function testACacheStorageIsAsLocalAsItsPool(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.local', new Definition(FilesystemAdapter::class));
        $container->setDefinition('cache.shared', new Definition(RedisAdapter::class));
        $container->setDefinition('storage.local', new Definition(CacheStorage::class, [new Reference('cache.local')]));
        $container->setDefinition('storage.named', new Definition('\\'.CacheStorage::class, ['$pool' => new Reference('cache.local')]));
        $container->setDefinition('storage.shared', new Definition(CacheStorage::class, [new Reference('cache.shared')]));
        $container->setDefinition('storage.inline', new Definition(CacheStorage::class, [new Definition(FilesystemAdapter::class)]));
        $container->setDefinition('storage.empty', new Definition(CacheStorage::class));

        $locality = new StoreLocality();

        self::assertTrue($locality->isLocal($container, 'storage.local'));
        self::assertTrue($locality->isLocal($container, 'storage.named'));
        self::assertFalse($locality->isLocal($container, 'storage.shared'));
        self::assertFalse($locality->isLocal($container, 'storage.inline'));
        self::assertFalse($locality->isLocal($container, 'storage.empty'));
    }

    /**
     * A storage given its pool both ways is read by position, as the container passes it.
     */
    public function testThePoolByPositionComesBeforeThePoolByName(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.local', new Definition(FilesystemAdapter::class));
        $container->setDefinition('cache.shared', new Definition(RedisAdapter::class));
        $container->setDefinition('storage.both', new Definition(CacheStorage::class, [new Reference('cache.local'), '$pool' => new Reference('cache.shared')]));

        self::assertTrue(new StoreLocality()->isLocal($container, 'storage.both'));
    }

    public function testAnotherClassTakingAPoolIsShared(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.local', new Definition(FilesystemAdapter::class));
        $container->setDefinition('store', new Definition(stdClass::class, [new Reference('cache.local')]));

        self::assertFalse(new StoreLocality()->isLocal($container, 'store'));
    }

    public function testASharedPoolAnUndefinedOneAndOneWithoutAClassAreShared(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.adapter.redis', new Definition(RedisAdapter::class));
        $container->setDefinition('cache.pool', new ChildDefinition('cache.adapter.redis'));
        $container->setDefinition('classless', new Definition());

        $locality = new StoreLocality();

        self::assertFalse($locality->isLocal($container, 'cache.pool'));
        self::assertFalse($locality->isLocal($container, 'missing'));
        self::assertFalse($locality->isLocal($container, 'classless'));
    }

    public function testALoopIsShared(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('one', new Definition(CacheStorage::class, [new Reference('two')]));
        $container->setDefinition('two', new Definition(CacheStorage::class, [new Reference('one')]));

        self::assertFalse(new StoreLocality()->isLocal($container, 'one'));
    }

    public function testTheChainIsBounded(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('pool', new Definition(FilesystemAdapter::class));

        $last = 'pool';

        for ($i = 1; $i < StoreLocality::MAX_DEPTH; ++$i) {
            $container->setDefinition('storage.'.$i, new Definition(CacheStorage::class, [new Reference($last)]));
            $last = 'storage.'.$i;
        }

        $locality = new StoreLocality();
        self::assertTrue($locality->isLocal($container, $last));

        $container->setDefinition('storage.over', new Definition(CacheStorage::class, [new Reference($last)]));
        self::assertFalse($locality->isLocal($container, 'storage.over'));
    }
}
