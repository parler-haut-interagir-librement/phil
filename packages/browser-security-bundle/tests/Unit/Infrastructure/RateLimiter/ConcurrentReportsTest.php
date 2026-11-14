<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure\RateLimiter;

use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\ExclusiveLockStore;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\InterleavingStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Two report bodies of one client, the second posted between the read and the write of the first, on a limit of one.
 */
#[CoversClass(PhilBrowserSecurityBundle::class)]
final class ConcurrentReportsTest extends TestCase
{
    public function testTheBuiltThrottleLetsOnlyOneOfTwoInterleavedBodiesPassUnderTheLock(): void
    {
        self::assertSame([true, false], $this->interleaved(true));
    }

    public function testWithoutALockFactoryBothInterleavedBodiesPass(): void
    {
        self::assertSame([true, true], $this->interleaved(false));
    }

    /**
     * @return array{bool, bool} whether the first body passed, then the second
     */
    private function interleaved(bool $withLocks): array
    {
        $loaded = new ContainerBuilder();
        $loaded->setParameter('kernel.bundles', []);
        $loaded->setParameter('kernel.debug', false);
        $extension = new PhilBrowserSecurityBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([['reporting' => ['rate_limit' => ['limit' => 1]]]], $loaded);

        // The factory as the bundle builds it, on a storage that runs the second body in the middle of the first.
        $storage = new InterleavingStorage();
        $container = new ContainerBuilder();
        $container->setDefinition('limiter', $loaded->getDefinition('phil_browser_security.report_limiter'))->replaceArgument(1, new Reference('storage'))->setPublic(true);
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
