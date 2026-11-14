<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use ArrayObject;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCacheNamespace;
use Phil\TenancyBundle\Infrastructure\Cache\TenantPools;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedPool;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedTagAwarePool;
use Phil\TenancyBundle\Infrastructure\Cache\TenantTags;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The same tag in two tenants and in the global namespace: invalidating it in one never reaches the others, over a
 * tag-aware adapter on a scoped pool (as `cache.app.taggable` over `cache.app`) and over a tag-aware pool scoped by the
 * decorator itself (as a natively tag-aware adapter).
 */
#[CoversClass(TenantScopedTagAwarePool::class)]
#[CoversClass(TenantTags::class)]
#[CoversClass(TenantScopedPool::class)]
#[CoversClass(TenantPools::class)]
#[CoversClass(TenantCacheNamespace::class)]
final class TenantScopedTagAwarePoolTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function layouts(): iterable
    {
        yield 'over a scoped pool' => [false];
        yield 'scoping a tag-aware pool' => [true];
    }

    #[DataProvider('layouts')]
    public function testInvalidatingATagInOneTenantLeavesTheOtherTenantAndTheGlobalNamespace(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);

        foreach ([null, 'retail', 'wholesale'] as $tenant) {
            $this->maybeIn($context, $tenant, static fn (): bool => $pool->save($pool->getItem('price')->set($tenant ?? 'global')->tag('prices')));
        }

        self::assertTrue($this->in($context, 'retail', static fn (): bool => $pool->invalidateTags(['prices'])));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => self::has($pool, 'price')));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => self::value($pool, 'price')));
        self::assertSame('global', self::value($pool, 'price'));

        self::assertTrue($pool->invalidateTags(['prices']));

        self::assertFalse(self::has($pool, 'price'));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => self::value($pool, 'price')));
    }

    #[DataProvider('layouts')]
    public function testTheSameKeyHoldsOneValuePerTenant(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);

        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('k')->set('retail')));

        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => self::has($pool, 'k')));
        self::assertFalse(self::has($pool, 'k'));
        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
    }

    public function testTagVersionsKeptInMemoryNeverCrossTenants(): void
    {
        // A tag-aware adapter keeps the versions it has just read for a moment: a worker reading a tag in one tenant,
        // then invalidating it in another, must not see either tenant's version in the other.
        $context = new TenantContext();
        $pool = $this->pool($context, false);

        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('k')->set('retail')->tag('t')));
        $this->in($context, 'wholesale', static fn (): bool => $pool->save($pool->getItem('k')->set('wholesale')->tag('t')));

        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
        $this->in($context, 'wholesale', static fn (): bool => $pool->invalidateTags(['t']));
        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => self::has($pool, 'k')));
    }

    #[DataProvider('layouts')]
    public function testTheContractsTagAndInvalidatePerTenant(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);
        $compute = static fn (string $value): callable => static function (ItemInterface $item) use ($value): string {
            $item->tag(['catalogue']);

            return $value;
        };

        self::assertSame('retail', $this->in($context, 'retail', static fn (): mixed => $pool->get('menu', $compute('retail'))));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => $pool->get('menu', $compute('wholesale'))));

        $metadata = null;
        self::assertSame('retail', $this->in($context, 'retail', static function () use ($pool, $compute, &$metadata): mixed {
            return $pool->get('menu', $compute('computed again'), null, $metadata);
        }));
        self::assertIsArray($metadata);
        self::assertSame(['catalogue' => 'catalogue'], $metadata[ItemInterface::METADATA_TAGS] ?? null, 'The metadata names the tags without the prefix.');

        $this->in($context, 'wholesale', static fn (): bool => $pool->invalidateTags(['catalogue']));

        self::assertSame('retail', $this->in($context, 'retail', static fn (): mixed => $pool->get('menu', $compute('computed again'))));
        self::assertSame('again', $this->in($context, 'wholesale', static fn (): mixed => $pool->get('menu', $compute('again'))));
        self::assertTrue($this->in($context, 'wholesale', static fn (): bool => $pool->delete('menu')));
        self::assertSame('retail', $this->in($context, 'retail', static fn (): mixed => $pool->get('menu', $compute('computed again'))));
    }

    #[DataProvider('layouts')]
    public function testAnItemReadNamesTheTagsItWasSavedWith(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);

        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('k')->set(1)->tag(['a', 'b'])));

        $item = $this->in($context, 'retail', static fn (): CacheItem => $pool->getItem('k'));
        self::assertSame(['a' => 'a', 'b' => 'b'], $item->getMetadata()[ItemInterface::METADATA_TAGS] ?? null);

        $items = $this->in($context, 'retail', static fn (): array => [...$pool->getItems(['k', 'missing'])]);
        self::assertSame(['k', 'missing'], array_keys($items));
        self::assertSame(['a' => 'a', 'b' => 'b'], $items['k']->getMetadata()[ItemInterface::METADATA_TAGS] ?? null);
        self::assertFalse($items['missing']->isHit());
    }

    public function testAnItemSavedAgainKeepsItsTags(): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, false);

        $this->in($context, 'retail', static function () use ($pool): void {
            $item = $pool->getItem('k')->set(1)->tag('t');
            $pool->save($item);
            $pool->save($item->set(2));
        });
        $this->in($context, 'retail', static fn (): bool => $pool->invalidateTags(['t']));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
    }

    public function testATagOfATenantsFormIsRefusedOnSave(): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, false);

        $this->expectException(InvalidArgumentException::class);

        $pool->save($pool->getItem('k')->set(1)->tag('tenant.retail.prices'));
    }

    public function testATagOfATenantsFormIsRefusedOnInvalidation(): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, false);

        $this->expectException(InvalidArgumentException::class);

        $this->in($context, 'wholesale', static fn (): bool => $pool->invalidateTags(['tenant-x']));
    }

    public function testATagWithAReservedCharacterIsRefusedOnInvalidation(): void
    {
        $pool = $this->pool(new TenantContext(), false);

        $this->expectException(InvalidArgumentException::class);

        $pool->invalidateTags(['a:b']);
    }

    #[DataProvider('layouts')]
    public function testDeletingAndClearingStayInTheTenant(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);

        foreach ([null, 'retail', 'wholesale'] as $tenant) {
            $this->maybeIn($context, $tenant, static function () use ($pool): void {
                $pool->save($pool->getItem('a')->set(1));
                $pool->save($pool->getItem('b')->set(2));
            });
        }

        $this->in($context, 'retail', static fn (): bool => $pool->deleteItem('a') && $pool->deleteItems(['b']));
        self::assertSame([false, false], $this->in($context, 'retail', static fn (): array => [self::has($pool, 'a'), self::has($pool, 'b')]));

        self::assertTrue($this->in($context, 'wholesale', static fn (): bool => $pool->clear()));
        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => self::has($pool, 'a')));
        self::assertTrue(self::has($pool, 'a'));
    }

    public function testADeferredSaveOverAScopedPoolIsSavedAtOnce(): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, false);

        $this->in($context, 'retail', static fn (): bool => $pool->saveDeferred($pool->getItem('k')->set('retail')->tag('t')));

        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => self::has($pool, 'k')));
        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
        self::assertTrue($pool->commit());
    }

    public function testADeferredSaveOverATagAwarePoolWaitsForItsTenantsCommit(): void
    {
        $activations = new ArrayObject();
        $context = new TenantContext($activations);
        $inner = new TagAwareAdapter(new ArrayAdapter());
        $pool = new TenantScopedTagAwarePool($inner, $context, true);
        $activations->append($pool);

        $context->enter(TenantId::fromString('retail'));
        self::assertTrue($pool->saveDeferred($pool->getItem('k')->set('retail')->tag('t')));
        self::assertFalse($inner->withSubNamespace('tenant.retail')->hasItem('k'), 'Deferred.');
        $context->enter(TenantId::fromString('wholesale'));

        self::assertFalse(self::has($pool, 'k'));
        self::assertTrue($inner->withSubNamespace('tenant.retail')->hasItem('k'), 'Committed when the worker left the tenant.');

        $pool->saveDeferred($pool->getItem('k')->set('wholesale'));
        self::assertTrue($pool->commit());
        self::assertTrue($inner->withSubNamespace('tenant.wholesale')->hasItem('k'));

        $pool->saveDeferred($pool->getItem('j')->set('wholesale'));
        $context->leave();
        self::assertTrue($inner->withSubNamespace('tenant.wholesale')->hasItem('j'), 'Committed when the worker left every tenant.');
    }

    public function testTheResetResetsThePool(): void
    {
        $items = new ArrayAdapter();
        $pool = new TenantScopedTagAwarePool(new TagAwareAdapter($items), new TenantContext(), true);
        $pool->save($pool->getItem('k')->set('kept'));

        $pool->reset();

        self::assertFalse($items->hasItem('k'), 'The adapter resets the pool under it, an array that forgets what it holds.');
    }

    public function testATagOfADeferredSaveIsPrefixedToo(): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, true);

        $this->in($context, 'retail', static function () use ($pool): void {
            $pool->saveDeferred($pool->getItem('k')->set('retail')->tag('prices'));
            $pool->commit();
        });

        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
        $this->in($context, 'retail', static fn (): bool => $pool->invalidateTags(['prices']));
        self::assertFalse($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')), 'The tag was saved with the prefix the invalidation adds.');
    }

    public function testTheResetBetweenRequestsCommits(): void
    {
        $directory = new TemporaryDirectory();
        $context = new TenantContext();
        $pool = new TenantScopedTagAwarePool(new TagAwareAdapter(new FilesystemAdapter('app', 0, $directory->path)), $context, true);

        try {
            $this->in($context, 'retail', static fn (): bool => $pool->saveDeferred($pool->getItem('k')->set('retail')));
            $pool->reset();

            self::assertTrue(new TagAwareAdapter(new FilesystemAdapter('app', 0, $directory->path))->withSubNamespace('tenant.retail')->hasItem('k'));
        } finally {
            $directory->remove();
        }
    }

    #[DataProvider('layouts')]
    public function testASubNamespaceLiesBelowTheTenants(bool $namespaced): void
    {
        $context = new TenantContext();
        $pool = $this->pool($context, $namespaced);
        $reports = $pool->withSubNamespace('reports');

        $this->in($context, 'retail', static fn (): bool => $reports->save($reports->getItem('k')->set('retail')->tag('t')));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => self::has($pool, 'k')));
        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => self::has($reports, 'k')));
        self::assertFalse(self::has($reports, 'k'));
        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($reports, 'k')));

        $this->in($context, 'wholesale', static fn (): bool => $reports->invalidateTags(['t']));
        self::assertTrue($this->in($context, 'retail', static fn (): bool => self::has($reports, 'k')));
        $this->in($context, 'retail', static fn (): bool => $reports->invalidateTags(['t']));
        self::assertFalse($this->in($context, 'retail', static fn (): bool => self::has($reports, 'k')));
    }

    public function testASubNamespaceOfATenantsFormIsRefused(): void
    {
        $pool = $this->pool(new TenantContext(), true);

        $this->expectException(InvalidArgumentException::class);

        $pool->withSubNamespace('tenant-abc');
    }

    public function testPruningPrunesThePool(): void
    {
        $directory = new TemporaryDirectory();
        $context = new TenantContext();

        try {
            self::assertTrue(new TenantScopedTagAwarePool(new TagAwareAdapter(new FilesystemAdapter('app', 0, $directory->path)), $context, true)->prune());
            self::assertTrue(new TenantScopedTagAwarePool(new TagAwareAdapter(new TenantScopedPool(new FilesystemAdapter('app', 0, $directory->path), $context)), $context)->prune());
        } finally {
            $directory->remove();
        }

        self::assertFalse($this->pool($context, true)->prune(), 'An array is not pruneable.');
        self::assertFalse($this->pool($context, false)->prune(), 'An array is not pruneable.');
    }

    /**
     * @phpstan-impure
     */
    private static function has(CacheItemPoolInterface $pool, string $key): bool
    {
        return $pool->hasItem($key);
    }

    /**
     * @phpstan-impure
     */
    private static function value(CacheItemPoolInterface $pool, string $key): mixed
    {
        return $pool->getItem($key)->get();
    }

    private function pool(TenantContext $context, bool $namespaced): TenantScopedTagAwarePool
    {
        if ($namespaced) {
            return new TenantScopedTagAwarePool(new TagAwareAdapter(new ArrayAdapter()), $context, true);
        }

        return new TenantScopedTagAwarePool(new TagAwareAdapter(new TenantScopedPool(new ArrayAdapter(), $context)), $context);
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function in(TenantContext $context, string $tenant, callable $work): mixed
    {
        return $context->within(TenantId::fromString($tenant), $work);
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function maybeIn(TenantContext $context, ?string $tenant, callable $work): mixed
    {
        return null === $tenant ? $work() : $this->in($context, $tenant, $work);
    }
}
