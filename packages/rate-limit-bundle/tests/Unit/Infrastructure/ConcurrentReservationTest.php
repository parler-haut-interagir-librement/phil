<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Infrastructure\DependencyInjection\RateLimitServices;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ExclusiveLockStore;
use Phil\RateLimitBundle\Tests\Unit\Fixture\InterleavingStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Two requests of one key, the second running between the read and the write of the first, on a limit of one.
 */
#[CoversClass(RateLimitServices::class)]
#[CoversClass(PhilRateLimitBundle::class)]
final class ConcurrentReservationTest extends TestCase
{
    public function testTheDefaultLockFactoryLetsOnlyOneOfTwoInterleavedRequestsPass(): void
    {
        self::assertSame([true, false], $this->interleaved([], true));
    }

    public function testANamedLockFactoryLetsOnlyOneOfTwoInterleavedRequestsPass(): void
    {
        self::assertSame([true, false], $this->interleaved(['lock_factory' => 'app.locks'], true, 'app.locks'));
    }

    public function testWithoutALockBothInterleavedRequestsPass(): void
    {
        self::assertSame([true, true], $this->interleaved([], false));
        self::assertSame([true, true], $this->interleaved(['lock_factory' => null], true));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{bool, bool} whether the first request passed, then the second
     */
    private function interleaved(array $config, bool $withLocks, string $locks = RateLimitServices::DEFAULT_LOCK): array
    {
        $storage = new InterleavingStorage();
        $container = new ContainerBuilder();
        $container->set('app.storage', $storage);
        $container->register('app.storage')->setSynthetic(true);

        if ($withLocks) {
            $container->register($locks, LockFactory::class)->setArguments([new ExclusiveLockStore()]);
        }

        $extension = new PhilRateLimitBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config + ['limiters' => ['once' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour', 'storage' => 'app.storage']]]], $container);
        $container->getDefinition('phil_rate_limit.factory.once')->setPublic(true);
        $container->compile(true);
        $container->set('app.storage', $storage);

        $factory = $container->get('phil_rate_limit.factory.once');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $factory);

        $second = null;
        $waiting = false;
        $storage->between(static function () use ($factory, &$second, &$waiting): void {
            try {
                $second = $factory->create('client')->consume()->isAccepted();
            } catch (LockConflictedException) {
                $waiting = true;
            }
        });

        $first = $factory->create('client')->consume()->isAccepted();

        if ($waiting) {
            $second = $factory->create('client')->consume()->isAccepted();
        }

        self::assertIsBool($second);

        return [$first, $second];
    }
}
