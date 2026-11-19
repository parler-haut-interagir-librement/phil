<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use Phil\Avatar\AvatarOptions;
use Phil\Avatar\Color;
use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Initials;
use Phil\Avatar\Palette;
use Phil\Avatar\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the reading and drawing rules: where a number, an anchor or a tie decides the result.
 */
#[CoversClass(AvatarOptions::class)]
#[CoversClass(Color::class)]
#[CoversClass(Initials::class)]
#[CoversClass(Palette::class)]
#[CoversClass(Random::class)]
final class EdgesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function colorsWithAPrefix(): iterable
    {
        yield 'a letter before' => ['x#fff'];
        yield 'a space before' => [' #ffffff'];
    }

    #[DataProvider('colorsWithAPrefix')]
    public function testAColorStartsWithItsHash(string $hex): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('A color is written #rrggbb or #rgb.'));

        Color::fromHex($hex);
    }

    public function testEveryDigitOfAShortColorIsDoubledInPlace(): void
    {
        $color = Color::fromHex('#1a2');

        self::assertSame('#11aa22', $color->toHex());
        self::assertSame([0x11, 0xAA, 0x22], [$color->red, $color->green, $color->blue]);
    }

    public function testEveryChannelOfALongColorIsReadFromItsOwnDigits(): void
    {
        $color = Color::fromHex('#12ab56');

        self::assertSame([0x12, 0xAB, 0x56], [$color->red, $color->green, $color->blue]);
    }

    public function testTheInkOfATieIsTheLightOne(): void
    {
        $light = Color::fromHex('#222222');
        $dark = Color::fromHex('#222222');
        $palette = new Palette([Color::fromHex('#ffffff')], $light, $dark);

        self::assertSame($light, $palette->inkFor(Color::fromHex('#808080')));
    }

    public function testThePaletteOfTheOptionsIsTheOneGiven(): void
    {
        $palette = Palette::named('pastel');

        self::assertSame($palette, new AvatarOptions(palette: $palette)->palette);
        self::assertSame(Palette::named('default')->backgrounds[0]->toHex(), new AvatarOptions()->palette->backgrounds[0]->toHex());
    }

    public function testAnOverlongNameIsCutInsideACharacterWithoutLosingItsInitials(): void
    {
        // The first kilobyte ends in the middle of an "é": the cut keeps the whole letters before it.
        $name = 'Ada L'.str_repeat('é', 600);

        self::assertSame('AL', Initials::of($name)->letters);
    }

    public function testAClusterThatDoesNotStartWithALetterGivesNoInitial(): void
    {
        // The Arabic number sign is a prefix of the digit after it: they are one cluster, whose first character is
        // neither a letter nor a digit.
        self::assertSame('B', Initials::of("\u{0600}1 b", 1)->letters);
        self::assertSame('B', Initials::of("\u{0600}1b", 1)->letters);
    }

    public function testALoneMarkIsSkippedForTheLetterAfterIt(): void
    {
        self::assertSame('A', Initials::of("\u{0301}ada", 1)->letters);
    }

    public function testTheDrawAtTheEndOfARangeIsStillAccepted(): void
    {
        // The draws of 'chance' seeds: the first draw of 1 to 100 is the number noted.
        self::assertTrue(new Random('c139')->chance(100));
        self::assertFalse(new Random('c139')->chance(99));
        self::assertTrue(new Random('c54')->chance(1));
        self::assertFalse(new Random('c54')->chance(0));
        self::assertTrue(new Random('c43')->chance(37));
        self::assertFalse(new Random('c43')->chance(36));
        self::assertFalse(new Random('c42')->chance(1));
        self::assertTrue(new Random('c42')->chance(2));
        self::assertFalse(new Random('c759')->chance(98));
        self::assertTrue(new Random('c759')->chance(99));
    }

    public function testADrawEqualToTheLimitIsDrawnAgain(): void
    {
        // The first word of "w0" is 2294151541, the second 1924337883. A range of 2294151541 numbers leaves out the
        // draws of 2294151541 and above, the first included.
        self::assertSame(1924337883, new Random('w0')->int(0, 2294151540));
    }

    public function testTheLimitOfARangeComesFromTheWholeSpaceOfDraws(): void
    {
        // A range of 6700417 numbers leaves out the draws of 640 × 6700417 and above, as 2^32 is not a multiple of
        // it. The first word of "l772", 4288600190, is one of them; the second, 4186602528, is not.
        self::assertSame(5542320, new Random('l772')->int(0, 6700416));
    }
}
