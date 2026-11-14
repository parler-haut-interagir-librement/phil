<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use ArrayObject;
use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCacheNamespace;
use Phil\TenancyBundle\Infrastructure\Cache\TenantPools;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedPool;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * Two tenants and the global namespace on one pool: the same key, a clear, deferred saves, a sub-namespace, a worker
 * that serves the tenants one after another.
 */
#[CoversClass(TenantScopedPool::class)]
#[CoversClass(TenantPools::class)]
#[CoversClass(TenantCacheNamespace::class)]
final class TenantScopedPoolTest extends TestCase
{
    private TemporaryDirectory $temporary;

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
        $this->directory = $this->temporary->path;
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testTheSameKeyHoldsOneValuePerTenant(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new ArrayAdapter(), $context);

        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('price')->set(10)));
        $this->in($context, 'wholesale', static fn () => self::assertFalse($pool->getItem('price')->isHit()));
        $this->in($context, 'wholesale', static fn (): bool => $pool->save($pool->getItem('price')->set(7)));

        self::assertSame(10, $this->in($context, 'retail', static fn (): mixed => $pool->getItem('price')->get()));
        self::assertSame(7, $this->in($context, 'wholesale', static fn (): mixed => $pool->getItem('price')->get()));
        self::assertFalse($pool->hasItem('price'), 'No tenant active: the global namespace, where nothing was saved.');
    }

    public function testWithNoTenantActiveTheGlobalNamespaceIsThePoolItself(): void
    {
        $context = new TenantContext();
        $inner = new ArrayAdapter();
        $pool = new TenantScopedPool($inner, $context);

        $pool->save($pool->getItem('currencies')->set(['CAD', 'EUR']));

        self::assertSame(['CAD', 'EUR'], $inner->getItem('currencies')->get());
        self::assertFalse($this->in($context, 'retail', static fn (): bool => $pool->hasItem('currencies')));

        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('only-retail')->set(true)));

        self::assertFalse($inner->hasItem('only-retail'));
        self::assertTrue($inner->withSubNamespace('tenant.retail')->hasItem('only-retail'));
    }

    public function testTheContractsOfTheCacheAreScopedToo(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new ArrayAdapter(), $context);

        self::assertSame('retail', $this->in($context, 'retail', static fn (): mixed => $pool->get('name', static fn (): string => 'retail')));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => $pool->get('name', static fn (): string => 'wholesale')));
        self::assertSame('retail', $this->in($context, 'retail', static fn (): mixed => $pool->get('name', static fn (): string => 'computed again')));

        self::assertTrue($this->in($context, 'retail', static fn (): bool => $pool->delete('name')));
        self::assertSame('again', $this->in($context, 'retail', static fn (): mixed => $pool->get('name', static fn (): string => 'again')));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => $pool->get('name', static fn (): string => 'computed again')));
    }

    public function testDeletingInOneTenantLeavesTheOther(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new ArrayAdapter(), $context);

        foreach (['retail', 'wholesale'] as $tenant) {
            $this->in($context, $tenant, static function () use ($pool): void {
                $pool->save($pool->getItem('a')->set(1));
                $pool->save($pool->getItem('b')->set(2));
            });
        }

        $this->in($context, 'retail', static fn (): bool => $pool->deleteItem('a') && $pool->deleteItems(['b']));

        self::assertSame([false, false], $this->in($context, 'retail', static fn (): array => [$pool->hasItem('a'), $pool->hasItem('b')]));
        self::assertSame([true, true], $this->in($context, 'wholesale', static fn (): array => [$pool->hasItem('a'), $pool->hasItem('b')]));
        self::assertSame(['a' => 1, 'b' => 2], $this->in($context, 'wholesale', static fn (): array => array_map(static fn (CacheItem $item): mixed => $item->get(), [...$pool->getItems(['a', 'b'])])));
    }

    public function testClearingATenantLeavesTheOtherTenantAndTheGlobalNamespace(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        $pool->save($pool->getItem('k')->set('global'));
        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('k')->set('retail')));
        $this->in($context, 'wholesale', static fn (): bool => $pool->save($pool->getItem('k')->set('wholesale')));

        self::assertTrue($this->in($context, 'retail', static fn (): bool => $pool->clear()));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => $pool->hasItem('k')));
        self::assertSame('wholesale', $this->in($context, 'wholesale', static fn (): mixed => $pool->getItem('k')->get()));
        self::assertSame('global', $pool->getItem('k')->get());
    }

    public function testClearingWithNoTenantActiveClearsEveryTenant(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        $pool->save($pool->getItem('k')->set('global'));
        $this->in($context, 'retail', static fn (): bool => $pool->save($pool->getItem('k')->set('retail')));

        self::assertTrue($pool->clear());

        self::assertFalse($pool->hasItem('k'));
        self::assertFalse($this->in($context, 'retail', static fn (): bool => $pool->hasItem('k')));
    }

    public function testAWorkerThatSwitchesTenantsCommitsWhatWasDeferredInTheTenantItWasDeferredIn(): void
    {
        $activations = new ArrayObject();
        $context = new TenantContext($activations);
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);
        $activations->append($pool);

        $context->enter(TenantId::fromString('retail'));
        $pool->saveDeferred($pool->getItem('k')->set('retail'));
        $context->enter(TenantId::fromString('wholesale'));

        self::assertFalse($pool->hasItem('k'));
        self::assertSame('retail', $this->fresh('retail')->getItem('k')->get(), 'Committed when the worker left the tenant.');
    }

    public function testCommitCommitsWhatEveryTenantDeferred(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        $pool->saveDeferred($pool->getItem('k')->set('global'));
        $this->in($context, 'retail', static fn (): bool => $pool->saveDeferred($pool->getItem('k')->set('retail')));
        $this->in($context, 'wholesale', static fn (): bool => $pool->saveDeferred($pool->getItem('k')->set('wholesale')));

        self::assertFalse($this->fresh('retail')->hasItem('k'));
        self::assertTrue($pool->commit());

        self::assertSame('retail', $this->fresh('retail')->getItem('k')->get());
        self::assertSame('wholesale', $this->fresh('wholesale')->getItem('k')->get());
        self::assertSame('global', new FilesystemAdapter('app', 0, $this->directory)->getItem('k')->get());
    }

    public function testWhatIsDeferredInTheGlobalNamespaceNeverLandsInATenants(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        $pool->saveDeferred($pool->getItem('secret')->set('global'));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => $pool->hasItem('secret')));
        self::assertFalse($this->fresh('retail')->hasItem('secret'));
        self::assertSame('global', new FilesystemAdapter('app', 0, $this->directory)->getItem('secret')->get());
    }

    public function testTheResetBetweenRequestsCommitsWhatWasDeferred(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        $this->in($context, 'retail', static fn (): bool => $pool->saveDeferred($pool->getItem('k')->set('retail')));
        $pool->saveDeferred($pool->getItem('k')->set('global'));
        $pool->reset();

        self::assertSame('retail', $this->fresh('retail')->getItem('k')->get());
        self::assertSame('global', new FilesystemAdapter('app', 0, $this->directory)->getItem('k')->get());
    }

    public function testLeavingATenantCommitsWhatItDeferred(): void
    {
        $activations = new ArrayObject();
        $context = new TenantContext($activations);
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);
        $activations->append($pool);

        $context->enter(TenantId::fromString('retail'));
        $pool->saveDeferred($pool->getItem('k')->set('retail'));
        $context->leave();

        self::assertSame('retail', $this->fresh('retail')->getItem('k')->get());
    }

    public function testASubNamespaceLiesBelowTheTenants(): void
    {
        $context = new TenantContext();
        $inner = new ArrayAdapter();
        $pool = new TenantScopedPool($inner, $context);
        $reports = $pool->withSubNamespace('reports');

        $this->in($context, 'retail', static fn (): bool => $reports->save($reports->getItem('k')->set('retail report')));
        $reports->save($reports->getItem('k')->set('global report'));

        self::assertFalse($this->in($context, 'retail', static fn (): bool => $pool->hasItem('k')));
        self::assertFalse($this->in($context, 'wholesale', static fn (): bool => $reports->hasItem('k')));
        self::assertSame('retail report', $inner->withSubNamespace('tenant.retail')->withSubNamespace('reports')->getItem('k')->get());
        self::assertSame('global report', $inner->withSubNamespace('reports')->getItem('k')->get());
        self::assertNotSame($pool, $reports);
    }

    public function testASubNamespaceOfATenantsFormIsRefused(): void
    {
        $pool = new TenantScopedPool(new ArrayAdapter(), new TenantContext());

        $this->expectException(InvalidArgumentException::class);

        $pool->withSubNamespace('tenant.retail');
    }

    public function testASubNamespaceWithAReservedCharacterIsRefused(): void
    {
        $pool = new TenantScopedPool(new ArrayAdapter(), new TenantContext());

        $this->expectException(InvalidArgumentException::class);

        $pool->withSubNamespace('a:b');
    }

    public function testPruningPrunesThePool(): void
    {
        $context = new TenantContext();
        $pool = new TenantScopedPool(new FilesystemAdapter('app', 0, $this->directory), $context);

        self::assertTrue($pool->prune());
        self::assertFalse(new TenantScopedPool(new ArrayAdapter(), $context)->prune(), 'An array is not pruneable.');
    }

    public function testTheResetResetsThePool(): void
    {
        $inner = new ArrayAdapter();
        $pool = new TenantScopedPool($inner, new TenantContext());
        $pool->save($pool->getItem('k')->set('kept'));

        $pool->reset();

        self::assertFalse($inner->hasItem('k'), 'An array adapter forgets what it holds when it is reset.');
    }

    public function testThePoolOfATenantIsKeptWhileInUse(): void
    {
        $pools = new TenantPools(new FilesystemAdapter('app', 0, $this->directory));
        $retail = TenantId::fromString('retail');

        self::assertSame($pools->of($retail), $pools->of($retail));
        self::assertSame($pools->of(null), $pools->of(null));
        self::assertSame($pools->root(), $pools->of(null));
        self::assertNotSame($pools->of($retail), $pools->of(TenantId::fromString('wholesale')));
    }

    public function testTheOldestTenantIsCommittedAndDroppedToMakeRoom(): void
    {
        $pools = new TenantPools(new FilesystemAdapter('app', 0, $this->directory));
        $first = $pools->of(TenantId::fromString('t0'));
        $first->saveDeferred($first->getItem('k')->set('t0'));

        for ($i = 1; $i < TenantPools::MAX_TENANTS; ++$i) {
            $pools->of(TenantId::fromString('t'.$i));
        }

        self::assertSame($first, $pools->of(TenantId::fromString('t0')), 'Still kept at the limit.');
        self::assertFalse($this->fresh('t0')->hasItem('k'));

        $pools->of(TenantId::fromString('t'.TenantPools::MAX_TENANTS));

        self::assertSame('t0', $this->fresh('t0')->getItem('k')->get(), 'Committed as it was dropped.');
        self::assertNotSame($first, $pools->of(TenantId::fromString('t0')));
    }

    public function testForgettingCommitsEveryPoolAndDropsThem(): void
    {
        $pools = new TenantPools(new FilesystemAdapter('app', 0, $this->directory), ['reports']);
        $retail = $pools->of(TenantId::fromString('retail'));
        $retail->saveDeferred($retail->getItem('k')->set('retail'));

        $global = $pools->of(null);

        $pools->forget();

        self::assertSame('retail', $this->fresh('retail')->withSubNamespace('reports')->getItem('k')->get());
        self::assertNotSame($retail, $pools->of(TenantId::fromString('retail')));
        self::assertNotSame($global, $pools->of(null));
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

    private function fresh(string $tenant): FilesystemAdapter
    {
        return new FilesystemAdapter('app', 0, $this->directory)->withSubNamespace(TenantCacheNamespace::of(TenantId::fromString($tenant)));
    }
}
