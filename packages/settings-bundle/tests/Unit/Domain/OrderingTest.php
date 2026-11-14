<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Domain;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Setting\Ordering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ordering::class)]
final class OrderingTest extends TestCase
{
    public function testWithoutWishesTheOrderIsTheDeclaration(): void
    {
        self::assertSame(['c', 'a', 'b'], Ordering::sort(['c' => $this->none(), 'a' => $this->none(), 'b' => $this->none()]));
        self::assertSame([], Ordering::sort([]));
    }

    public function testAnItemComesBeforeOrAfterOthers(): void
    {
        self::assertSame(['b', 'a', 'c'], Ordering::sort(['a' => ['before' => [], 'after' => ['b']], 'b' => $this->none(), 'c' => $this->none()]));
        self::assertSame(['a', 'c', 'b'], Ordering::sort(['a' => $this->none(), 'b' => $this->none(), 'c' => ['before' => ['b'], 'after' => []]]));
        self::assertSame(['d', 'a', 'b', 'c'], Ordering::sort([
            'a' => $this->none(),
            'b' => ['before' => [], 'after' => ['a', 'a']],
            'c' => $this->none(),
            'd' => ['before' => ['a', 'b'], 'after' => []],
        ]));
    }

    public function testANameNotDeclaredIsPassedOver(): void
    {
        self::assertSame(['a', 'b'], Ordering::sort(['a' => ['before' => ['missing'], 'after' => ['gone']], 'b' => $this->none()]));
    }

    public function testAnItemPlacedEarlierIsNotPlacedAgain(): void
    {
        self::assertSame(['c', 'a', 'b'], Ordering::sort(['a' => ['before' => [], 'after' => ['c']], 'b' => ['before' => [], 'after' => ['c', 'a']], 'c' => $this->none()]));
    }

    public function testAnItemIsPlacedOnceHoweverManyWaitForIt(): void
    {
        $items = ['a0' => self::none(), 'b0' => self::none()];

        for ($level = 1; $level <= 40; ++$level) {
            $items['a'.$level] = ['before' => [], 'after' => ['a'.($level - 1), 'b'.($level - 1)]];
            $items['b'.$level] = ['before' => [], 'after' => ['a'.($level - 1), 'b'.($level - 1)]];
        }

        $sorted = Ordering::sort(array_reverse($items, true));

        self::assertCount(82, $sorted);
        self::assertSame(['a0', 'b0'], \array_slice($sorted, 0, 2));
        self::assertSame(['b40', 'a40'], [$sorted[80], $sorted[81]]);
    }

    public function testACircleIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('"b", "c" ask to come before one another in a circle.');

        Ordering::sort(['a' => $this->none(), 'b' => ['before' => ['c'], 'after' => []], 'c' => ['before' => ['b'], 'after' => []]]);
    }

    /**
     * @return array{before: list<string>, after: list<string>}
     */
    private function none(): array
    {
        return ['before' => [], 'after' => []];
    }
}
