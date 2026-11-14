<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCachePass;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedPool;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedTagAwarePool;
use Phil\TenancyBundle\PhilTenancyBundle;
use Phil\TenancyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The pools of the test application, scoped to the active tenant: `cache.app`, `app.prices` and the tag-aware
 * `app.catalogue`, both built on it, and `app.exchange_rates`, shared when `global_pools` lists it.
 */
#[CoversClass(TenantCachePass::class)]
#[CoversClass(PhilTenancyBundle::class)]
final class CacheTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testCacheAppAndThePoolsBuiltOnItAreScoped(): void
    {
        self::bootKernel();

        self::assertInstanceOf(TenantScopedPool::class, self::getContainer()->get('cache.app'));
        self::assertInstanceOf(TenantScopedPool::class, self::getContainer()->get('app.prices'));
        self::assertInstanceOf(TenantScopedPool::class, self::getContainer()->get('app.exchange_rates'));
        self::assertInstanceOf(TenantScopedTagAwarePool::class, self::getContainer()->get('app.catalogue'));
    }

    public function testTwoTenantsNeverShareAnEntry(): void
    {
        self::bootKernel();
        $context = $this->context();
        $cache = $this->cache('cache.app');
        $cache->delete('greeting');

        self::assertSame('retail', $context->within(TenantId::fromString('retail'), static fn (): mixed => $cache->get('greeting', static fn (): string => 'retail')));
        self::assertSame('wholesale', $context->within(TenantId::fromString('wholesale'), static fn (): mixed => $cache->get('greeting', static fn (): string => 'wholesale')));
        self::assertSame('global', $cache->get('greeting', static fn (): string => 'global'));
        self::assertSame('retail', $context->within(TenantId::fromString('retail'), static fn (): mixed => $cache->get('greeting', static fn (): string => 'again')));

        $cache->delete('greeting');
        $context->within(TenantId::fromString('retail'), static fn (): bool => $cache->delete('greeting'));
        $context->within(TenantId::fromString('wholesale'), static fn (): bool => $cache->delete('greeting'));
    }

    public function testInvalidatingATagInOneTenantLeavesTheOther(): void
    {
        self::bootKernel();
        $context = $this->context();
        $catalogue = self::getContainer()->get('app.catalogue');
        self::assertInstanceOf(TagAwareCacheInterface::class, $catalogue);
        $compute = static fn (string $value): callable => static function (ItemInterface $item) use ($value): string {
            $item->tag('menu');

            return $value;
        };

        foreach (['retail', 'wholesale'] as $tenant) {
            $context->within(TenantId::fromString($tenant), static fn (): bool => $catalogue->invalidateTags(['menu']));
            $context->within(TenantId::fromString($tenant), static fn (): mixed => $catalogue->get('menu', $compute($tenant)));
        }

        $context->within(TenantId::fromString('retail'), static fn (): bool => $catalogue->invalidateTags(['menu']));

        self::assertSame('recomputed', $context->within(TenantId::fromString('retail'), static fn (): mixed => $catalogue->get('menu', $compute('recomputed'))));
        self::assertSame('wholesale', $context->within(TenantId::fromString('wholesale'), static fn (): mixed => $catalogue->get('menu', $compute('recomputed'))));
    }

    public function testAGlobalPoolIsSharedByEveryTenant(): void
    {
        self::bootKernel(['configuration' => ['cache' => ['global_pools' => ['app.exchange_rates', 'app.catalogue']]]]);
        $context = $this->context();
        $rates = $this->cache('app.exchange_rates');
        $rates->delete('CAD');

        self::assertNotInstanceOf(TenantScopedPool::class, $rates);
        self::assertNotInstanceOf(TenantScopedTagAwarePool::class, self::getContainer()->get('app.catalogue'));
        self::assertInstanceOf(TenantScopedPool::class, self::getContainer()->get('app.prices'));

        self::assertSame(1.37, $context->within(TenantId::fromString('retail'), static fn (): mixed => $rates->get('CAD', static fn (): float => 1.37)));
        self::assertSame(1.37, $context->within(TenantId::fromString('wholesale'), static fn (): mixed => $rates->get('CAD', static fn (): float => 0.0)));
        self::assertSame(1.37, $rates->get('CAD', static fn (): float => 0.0));
        $rates->delete('CAD');
    }

    public function testTheCacheCanBeLeftAlone(): void
    {
        self::bootKernel(['configuration' => ['cache' => false]]);

        self::assertNotInstanceOf(TenantScopedPool::class, self::getContainer()->get('cache.app'));
        self::assertNotInstanceOf(TenantScopedPool::class, self::getContainer()->get('app.prices'));
    }

    public function testAGlobalPoolThatIsNoPoolStopsTheKernel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The pool "app.missing" of phil_tenancy.cache.global_pools is not a cache pool.');

        self::bootKernel(['configuration' => ['cache' => ['global_pools' => ['app.missing']]]]);
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var array<string, mixed> $configuration */
        $configuration = $options['configuration'] ?? [];

        return new TestKernel('test', true, $configuration);
    }

    private function context(): TenantContext
    {
        $context = self::getContainer()->get(TenantContext::class);
        self::assertInstanceOf(TenantContext::class, $context);

        return $context;
    }

    private function cache(string $id): CacheInterface
    {
        $cache = self::getContainer()->get($id);
        self::assertInstanceOf(CacheInterface::class, $cache);

        return $cache;
    }
}
