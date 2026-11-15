<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Channel;

use Phil\CommerceBundle\Domain\Channel\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Visibility::class)]
final class VisibilityTest extends TestCase
{
    public function testEachLevelSaysWhetherItIsListedAndReachable(): void
    {
        self::assertSame([true, true], [Visibility::Listed->isListed(), Visibility::Listed->isReachable()]);
        self::assertSame([false, true], [Visibility::Unlisted->isListed(), Visibility::Unlisted->isReachable()]);
        self::assertSame([false, false], [Visibility::Hidden->isListed(), Visibility::Hidden->isReachable()]);
        self::assertSame(['listed', 'unlisted', 'hidden'], array_map(static fn (Visibility $visibility): string => $visibility->value, Visibility::cases()));
    }

    /**
     * @return iterable<string, array{Visibility, Visibility, Visibility}>
     */
    public static function pairs(): iterable
    {
        yield 'listed and listed' => [Visibility::Listed, Visibility::Listed, Visibility::Listed];
        yield 'listed and unlisted' => [Visibility::Listed, Visibility::Unlisted, Visibility::Unlisted];
        yield 'unlisted and listed' => [Visibility::Unlisted, Visibility::Listed, Visibility::Unlisted];
        yield 'unlisted and unlisted' => [Visibility::Unlisted, Visibility::Unlisted, Visibility::Unlisted];
        yield 'listed and hidden' => [Visibility::Listed, Visibility::Hidden, Visibility::Hidden];
        yield 'hidden and listed' => [Visibility::Hidden, Visibility::Listed, Visibility::Hidden];
        yield 'unlisted and hidden' => [Visibility::Unlisted, Visibility::Hidden, Visibility::Hidden];
        yield 'hidden and unlisted' => [Visibility::Hidden, Visibility::Unlisted, Visibility::Hidden];
        yield 'hidden and hidden' => [Visibility::Hidden, Visibility::Hidden, Visibility::Hidden];
    }

    #[DataProvider('pairs')]
    public function testTheStricterOfTwoWins(Visibility $one, Visibility $other, Visibility $expected): void
    {
        self::assertSame($expected, $one->stricter($other));
    }
}
