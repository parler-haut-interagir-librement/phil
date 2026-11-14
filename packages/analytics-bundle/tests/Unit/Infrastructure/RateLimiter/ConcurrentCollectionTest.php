<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\RateLimiter;

use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsServices;
use Phil\AnalyticsBundle\PhilAnalyticsBundle;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\ExclusiveLockStore;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InterleavingStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Two sends of one client, the second arriving between the read and the write of the first, on a limit of one.
 */
#[CoversClass(AnalyticsServices::class)]
final class ConcurrentCollectionTest extends TestCase
{
    public function testTheBuiltLimiterLetsOnlyOneOfTwoInterleavedSendsPassUnderTheLock(): void
    {
        self::assertSame([true, false], $this->interleaved(true));
    }

    public function testWithoutALockFactoryBothInterleavedSendsPass(): void
    {
        self::assertSame([true, true], $this->interleaved(false));
    }

    /**
     * @return array{bool, bool} whether the first send passed, then the second
     */
    private function interleaved(bool $withLocks): array
    {
        $loaded = new ContainerBuilder();
        $extension = new PhilAnalyticsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([['rate_limiter' => ['limit' => 1]]], $loaded);

        // The factory as the bundle builds it, on a storage that runs the second send in the middle of the first.
        $storage = new InterleavingStorage();
        $container = new ContainerBuilder();
        $container->setDefinition('limiter', $loaded->getDefinition(AnalyticsServices::LIMITER_FACTORY))->replaceArgument(1, new Reference('storage'))->setPublic(true);
        $container->register('storage')->setSynthetic(true);

        if ($withLocks) {
            $container->register('lock.factory', LockFactory::class)->setArguments([new ExclusiveLockStore()]);
        }

        $container->compile();
        $container->set('storage', $storage);
        $factory = $container->get('limiter');
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
