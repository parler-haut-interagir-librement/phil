<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\CurlyQuote;
use Phil\Typography\Fixer\Dimension;
use Phil\Typography\Fixer\Ellipsis;
use Phil\Typography\Fixer\Fixer;
use Phil\Typography\Fixer\NoSpaceBeforeComma;
use Phil\Typography\Fixer\Trademark;
use Phil\Typography\Fixer\UnicodeNormalization;
use Phil\Typography\Fixer\Unit;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ellipsis::class)]
#[CoversClass(Dimension::class)]
#[CoversClass(CurlyQuote::class)]
#[CoversClass(NoSpaceBeforeComma::class)]
#[CoversClass(Trademark::class)]
#[CoversClass(Unit::class)]
#[CoversClass(UnicodeNormalization::class)]
final class SimpleFixersTest extends TestCase
{
    private const string NB = "\u{A0}";

    /**
     * @return iterable<string, array{Fixer, string, string}>
     */
    public static function cases(): iterable
    {
        yield 'ellipsis' => [new Ellipsis(), 'Wait... and . . . so....', 'Wait… and … so….'];
        yield 'two stops' => [new Ellipsis(), 'a.. b', 'a.. b'];
        yield 'dimension' => [new Dimension(), '1920 x 1080, 1920x1080, 3 X 4', '1920 × 1080, 1920×1080, 3 × 4'];
        yield 'dimension with units' => [new Dimension(), '20cm x 30cm, 20 cm x 30 cm, 6\' x 8\', 5" x 7"', '20cm × 30cm, 20 cm × 30 cm, 6\' × 8\', 5" × 7"'];
        yield 'dimension with decimals' => [new Dimension(), '2.5 x 3,5 x 4', '2.5 × 3,5 × 4'];
        yield 'dimension chain' => [new Dimension(), '10x20x30', '10×20×30'];
        yield 'dimension other units' => [new Dimension(), '1mm x 2km x 3dm x 4m x 5in x 6ft x 7px x 8pt x 9em x 1rem x 2″ x 3′ x 4', '1mm × 2km × 3dm × 4m × 5in × 6ft × 7px × 8pt × 9em × 1rem × 2″ × 3′ × 4'];
        yield 'hexadecimal' => [new Dimension(), '0x1F and 0x20', '0x1F and 0x20'];
        yield 'zero by' => [new Dimension(), '0 x 1', '0 × 1'];
        yield 'not after letters' => [new Dimension(), 'A4x5, v2x3, 1.2x3', 'A4x5, v2x3, 1.2×3'];
        yield 'not after a decimal mark' => [new Dimension(), '.5x3 ,5x3', '.5x3 ,5x3'];
        yield 'not before a word' => [new Dimension(), '3 x a, 3x, x3, 3 xx 4, 3  x 4, 3 x  4', '3 x a, 3x, x3, 3 xx 4, 3  x 4, 3 x  4'];
        yield 'unit word not a unit' => [new Dimension(), '3 mmm x 4, 3 cmx 4', '3 mmm x 4, 3 cmx 4'];
        yield 'apostrophe' => [new CurlyQuote(), "l'été, don't, 'quoted'", 'l’été, don’t, ’quoted’'];
        yield 'comma' => [new NoSpaceBeforeComma(), 'word , word ,word'.self::NB.',x', 'word, word, word, x'];
        yield 'comma before a number' => [new NoSpaceBeforeComma(), 'a ,5 and 1,5', 'a,5 and 1,5'];
        yield 'comma at the start' => [new NoSpaceBeforeComma(), ' , start', ' , start'];
        yield 'trademark' => [new Trademark(), 'Brand(tm), Brand (TM), Brand  (Tm) and Name (r) Name(R)', 'Brand™, Brand™, Brand™ and Name® Name®'];
        yield 'copyright' => [new Trademark(), '(c) 2026, (C)2026, © 2026, (p) 1999, (P)1999', '©'.self::NB.'2026, ©'.self::NB.'2026, ©'.self::NB.'2026, ℗'.self::NB.'1999, ℗'.self::NB.'1999'];
        yield 'list items' => [new Trademark(), '(a) one (c) three (p) page (c)  2026 © x', '(a) one (c) three (p) page (c)  2026 © x'];
        yield 'unit' => [new Unit(), '50 km, 20 °C, 75 %, 12,50 $, 3 min, 5 mm, 10 kWh', '50'.self::NB.'km, 20'.self::NB.'°C, 75'.self::NB.'%, 12,50'.self::NB.'$, 3'.self::NB.'min, 5'.self::NB.'mm, 10'.self::NB.'kWh'];
        yield 'unit with a slash or a dot' => [new Unit(), '90 km/h, 5 m. 2 m/s', '90'.self::NB.'km/h, 5'.self::NB.'m. 2'.self::NB.'m/s'];
        yield 'not a unit' => [new Unit(), '5 mice, 3 apples, 2 km2, 20°C, 2  km, a 5', '5 mice, 3 apples, 2 km2, 20°C, 2  km, a 5'];
        yield 'normalization' => [new UnicodeNormalization(), "e\u{301}te\u{301} A\u{30A}", 'été Å'];
    }

    #[DataProvider('cases')]
    public function testTheFixer(Fixer $fixer, string $text, string $expected): void
    {
        self::assertSame([$expected], $fixer->fix(Run::of($text))->texts);
    }

    public function testEveryUnitIsHeld(): void
    {
        foreach (Unit::UNITS as $unit) {
            self::assertSame(['3'.self::NB.$unit.' x'], new Unit()->fix(Run::of('3 '.$unit.' x'))->texts, $unit);
        }
    }

    public function testTheFixersSeeTheNeighbouringParts(): void
    {
        self::assertSame(['..', '.'], new Ellipsis()->fix(new Run(['..', '.']))->texts);
        self::assertSame(['3', ' × ', '4'], new Dimension()->fix(new Run(['3', ' x ', '4']))->texts);
        self::assertSame(['a', 'x', 'b'], new Dimension()->fix(new Run(['a', 'x', 'b']))->texts);
        self::assertSame(['word', ',', ' x'], new NoSpaceBeforeComma()->fix(new Run(['word', ',', ' x']))->texts);
        self::assertSame(['5', ' ', 'km'], new Unit()->fix(new Run(['5', ' ', 'km']))->texts);
        self::assertSame(['l', '’été', "'"], new CurlyQuote()->fix(new Run(['l', "'été", "'"], [false, false, true]))->texts);
    }

    public function testNormalizationKeepsTextThatIsNotUtf8(): void
    {
        self::assertSame(["\xFF"], new UnicodeNormalization()->fix(Run::of("\xFF"))->texts);
    }
}
