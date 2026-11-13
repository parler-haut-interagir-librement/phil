<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StandardFont::class)]
#[CoversClass(PageSize::class)]
#[CoversClass(Color::class)]
#[CoversClass(Metadata::class)]
#[CoversClass(InvalidDocument::class)]
final class ValuesTest extends TestCase
{
    public function testHelveticaMeasuresEachCharacterAtItsWidth(): void
    {
        $regular = StandardFont::Helvetica;

        self::assertEqualsWithDelta(25.02, $regular->width('12.50', 10), 0.001);
        self::assertEqualsWithDelta(2.78, $regular->width(' ', 10), 0.001);
        self::assertEqualsWithDelta(5.84, $regular->width('~', 10), 0.001);
        self::assertEqualsWithDelta(9.44, $regular->width('W', 10), 0.001);
        self::assertEqualsWithDelta(2.22, $regular->width('i', 10), 0.001);
        self::assertEqualsWithDelta(5.56, $regular->width('é', 10), 0.001);
        self::assertEqualsWithDelta(6.67, $regular->width('É', 10), 0.001);
        self::assertEqualsWithDelta(3.33, $regular->width("\u{2212}", 10), 0.001, 'The minus sign is measured as the hyphen it prints as.');
        self::assertEqualsWithDelta(5.56, $regular->width('漢', 10), 0.001, 'A character Windows-1252 lacks is measured as the "?" it prints as.');
        self::assertEqualsWithDelta(10.0, $regular->width("\x7F", 10), 0.001, 'A byte without a known width counts as the widest letters.');
        self::assertEqualsWithDelta(0.0, $regular->width('', 10), 0.001);
    }

    public function testHelveticaBoldHasItsOwnWidths(): void
    {
        $bold = StandardFont::HelveticaBold;

        self::assertEqualsWithDelta(6.11, $bold->width('?', 10), 0.001);
        self::assertEqualsWithDelta(6.11, $bold->width('ö', 10), 0.001);
        self::assertEqualsWithDelta(7.22, $bold->width('À', 10), 0.001);
        self::assertEqualsWithDelta(2.78, $bold->width('’', 10), 0.001);
        self::assertEqualsWithDelta(12.0, $bold->width("\x7F", 12), 0.001);
        self::assertSame('Helvetica-Bold', $bold->value);
    }

    public function testCharactersWindows1252LacksAreWrittenAsTheirNearest(): void
    {
        self::assertSame("Vendredi fou -20\xA0%", StandardFont::encode("Vendredi fou \u{2212}20\u{202F}%"));
        self::assertSame("Bienvenue -5 \$ - 1\xA0000 \x96  x  y ?", StandardFont::encode("Bienvenue \u{2010}5\u{2009}\$ \u{2011} 1\u{2007}000 \u{2012}\u{200A}\u{2002}x\u{2003} y 漢"));
    }

    public function testPageSizes(): void
    {
        self::assertSame([612.0, 792.0], $this->sides(PageSize::letter()));
        self::assertSame([595.0, 842.0], $this->sides(PageSize::a4()));
        self::assertSame([420.0, 595.0], $this->sides(PageSize::a5()));
        self::assertSame([842.0, 595.0], $this->sides(PageSize::a4()->landscape()));
        self::assertSame([842.0, 595.0], $this->sides(PageSize::a4()->landscape()->landscape()));
        self::assertSame([396.0, 612.0], $this->sides(PageSize::of(396, 612)));
        self::assertSame([419.53, 595.28], $this->sides(PageSize::ofMillimetres(148, 210)));
        self::assertSame([1.0, 14_400.0], $this->sides(PageSize::of(1, 14_400)));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function invalidSizes(): iterable
    {
        yield 'too narrow' => [0.9, 100];
        yield 'too tall' => [100, 14_400.5];
    }

    #[DataProvider('invalidSizes')]
    public function testAPageSideIsBetweenOneAndTheMaximum(float $width, float $height): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageMatches('/\AA page side is between 1 and 14400 points; [\d.]+ is not\.\z/');

        PageSize::of($width, $height);
    }

    public function testColours(): void
    {
        self::assertSame('0.2 g', Color::gray(51)->operator(false));
        self::assertSame('0.2 G', Color::gray(51)->operator(true));
        self::assertSame('1 0.502 0 rg', Color::rgb(255, 128, 0)->operator(false));
        self::assertSame('1 0.502 0 RG', Color::rgb(255, 128, 0)->operator(true));
        self::assertSame('0.2 0.4 0.6 rg', Color::hex('#336699')->operator(false));
        self::assertSame('0.2 0.4 0.6 rg', Color::hex('#369')->operator(false));
        self::assertSame('0 g', Color::black()->operator(false));
        self::assertSame('0.2 0.4 0.8 rg', Color::hex('#3366CC')->operator(false), 'The digits may be upper case.');
        self::assertSame('0.004 g', Color::gray(1)->operator(false), 'A component is written to three decimals.');
    }

    /**
     * @return iterable<string, array{callable(): Color, string}>
     */
    public static function invalidColours(): iterable
    {
        yield 'grey above' => [static fn (): Color => Color::gray(256), 'A colour component is between 0 and 255; 256 is not.'];
        yield 'red below' => [static fn (): Color => Color::rgb(-1, 0, 0), 'A colour component is between 0 and 255; -1 is not.'];
        yield 'green above' => [static fn (): Color => Color::rgb(0, 300, 0), 'A colour component is between 0 and 255; 300 is not.'];
        yield 'blue above' => [static fn (): Color => Color::rgb(0, 0, 999), 'A colour component is between 0 and 255; 999 is not.'];
        yield 'hex without #' => [static fn (): Color => Color::hex('336699'), 'A colour is written #rrggbb or #rgb; "336699" is not.'];
        yield 'hex of four digits' => [static fn (): Color => Color::hex('#3366'), 'A colour is written #rrggbb or #rgb; "#3366" is not.'];
        yield 'hex with a letter past f' => [static fn (): Color => Color::hex('#33669g'), 'A colour is written #rrggbb or #rgb; "#33669g" is not.'];
        yield 'hex with a newline' => [static fn (): Color => Color::hex("#336699\n"), "A colour is written #rrggbb or #rgb; \"#336699\n\" is not."];
    }

    #[DataProvider('invalidColours')]
    public function testAnInvalidColourIsRefused(callable $colour, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        $colour();
    }

    public function testMetadataWritesOnlyWhatIsGiven(): void
    {
        self::assertSame(['Producer' => '(phil/paged-media)'], new Metadata()->entries());
        self::assertSame(['Title' => '(A)', 'Producer' => '(phil/paged-media)'], new Metadata('A', '')->entries());
        self::assertSame('(D:20261105193015Z)', new Metadata(createdAt: new DateTimeImmutable('2026-11-05 19:30:15', new DateTimeZone('UTC')))->entries()['CreationDate']);
        self::assertSame("(D:20261105193015+05'30')", new Metadata(createdAt: new DateTimeImmutable('2026-11-05 19:30:15', new DateTimeZone('Asia/Kolkata')))->entries()['CreationDate']);
        self::assertSame('(a \\\\ b \\( c \\))', Metadata::text('a \\ b ( c )'));
        self::assertSame('<FEFF00E9>', Metadata::text('é'));
        self::assertSame('<FEFFD83DDE00>', Metadata::text('😀'));
        self::assertSame('<FEFF0061000A>', Metadata::text("a\n"));
        self::assertSame('()', Metadata::text(''));
    }

    /**
     * @return array{float, float}
     */
    private function sides(PageSize $size): array
    {
        return [$size->width, $size->height];
    }
}
