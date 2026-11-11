<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Color;

use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Exception\InvalidColor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Oklch::class)]
final class OklchTest extends TestCase
{
    public function testItReadsTheWaysCssWritesIt(): void
    {
        self::assertEquals(new Oklch(0.4, 0.12, 195.0), Oklch::fromCss('oklch(0.4 0.12 195)'));
        self::assertEquals(new Oklch(0.4, 0.12, 195.0), Oklch::fromCss(' OKLCH( 40%  0.12  195deg ) '));
        self::assertEquals(new Oklch(0.5, 0.0, 0.0, 0.25), Oklch::fromCss('oklch(.5 0 0 / 0.25)'));
        self::assertEquals(new Oklch(0.5, 0.0, 0.0, 0.25), Oklch::fromCss('oklch(0.5 0 0/25%)'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notOklch(): iterable
    {
        yield 'another space' => ['rgb(0 0 0)'];
        yield 'a missing channel' => ['oklch(0.5 0.1)'];
        yield 'commas' => ['oklch(0.5, 0.1, 30)'];
        yield 'a word' => ['oklch(none 0.1 30)'];
        yield 'text after' => ['oklch(0.5 0.1 30) red'];
    }

    #[DataProvider('notOklch')]
    public function testItRefusesWhatIsNotOklch(string $value): void
    {
        $this->expectException(InvalidColor::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an OKLCH colour', $value));

        Oklch::fromCss($value);
    }

    public function testWhiteAndBlackAreTheEndsOfTheLuminanceAndOfTheContrast(): void
    {
        $white = new Oklch(1.0, 0.0, 0.0);
        $black = new Oklch(0.0, 0.0, 0.0);

        self::assertEqualsWithDelta(1.0, $white->relativeLuminance(), 0.0001);
        self::assertEqualsWithDelta(0.0, $black->relativeLuminance(), 0.0001);
        self::assertEqualsWithDelta(21.0, $white->contrastWith($black), 0.01);
        self::assertEqualsWithDelta(21.0, $black->contrastWith($white), 0.01);
        self::assertEqualsWithDelta(1.0, $white->contrastWith($white), 0.0001);
    }

    public function testAGreyHasTheCubeOfItsLightnessAsLuminance(): void
    {
        self::assertEqualsWithDelta(0.125, new Oklch(0.5, 0.0, 0.0)->relativeLuminance(), 0.001);
    }

    public function testItConvertsToLinearSrgb(): void
    {
        // A pure sRGB red, in OKLCH.
        [$red, $green, $blue] = new Oklch(0.627955, 0.257683, 29.2339)->toLinearSrgb();

        self::assertEqualsWithDelta(1.0, $red, 0.001);
        self::assertEqualsWithDelta(0.0, $green, 0.001);
        self::assertEqualsWithDelta(0.0, $blue, 0.001);
    }

    public function testItTellsAColourOutsideTheSrgbGamut(): void
    {
        self::assertTrue(new Oklch(0.5, 0.1, 30.0)->isInSrgbGamut());
        self::assertFalse(new Oklch(0.5, 0.4, 30.0)->isInSrgbGamut());
        self::assertFalse(new Oklch(1.2, 0.0, 0.0)->isInSrgbGamut());
        self::assertTrue(new Oklch(1.00001, 0.0, 0.0)->isInSrgbGamut(), 'within the rounding tolerance');
        self::assertFalse(new Oklch(1.00001, 0.0, 0.0)->isInSrgbGamut(0.0));
        self::assertTrue(new Oklch(1.0, 0.0, 0.0)->isInSrgbGamut(0.0), 'white is at the upper end of every channel');
        self::assertTrue(new Oklch(0.0, 0.0, 0.0)->isInSrgbGamut(0.0), 'black is at the lower end of every channel');
    }

    public function testTheLuminanceClipsWhatIsOutsideTheGamut(): void
    {
        self::assertEqualsWithDelta(1.0, new Oklch(1.5, 0.0, 0.0)->relativeLuminance(), 0.0001);
    }
}
