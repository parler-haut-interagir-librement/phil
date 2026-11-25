<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use FilesystemIterator;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Domain\Model\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SplFileInfo;

/**
 * `Order::force()` moves an order past its workflow without asking anyone: it is internal, and `OrderLifecycle`, which
 * asks `TransitionForcing`, journals and announces, is its one caller in the bundle.
 */
#[CoversClass(Order::class)]
#[CoversClass(OrderLifecycle::class)]
final class OrderForcingTest extends TestCase
{
    public function testForcingAnOrderIsNotPublicApi(): void
    {
        $comment = (string) new ReflectionMethod(Order::class, 'force')->getDocComment();

        self::assertMatchesRegularExpression('/^\s*\*\s*@internal\b/m', $comment);
        self::assertStringContainsString('`OrderLifecycle::force()` is the one caller', $comment);
    }

    public function testOnlyTheLifecycleForcesAnOrder(): void
    {
        $callers = [];
        $source = \dirname(__DIR__, 4).'/src';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);

            if ('php' === $file->getExtension() && 1 === preg_match('/->force\(/', (string) file_get_contents($file->getPathname()))) {
                $callers[] = substr($file->getPathname(), \strlen($source) + 1);
            }
        }

        self::assertSame(['Application/OrderLifecycle.php'], $callers);
    }
}
