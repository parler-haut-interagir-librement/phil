<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Override;
use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Card\GdTextMeasure;
use Phil\SocialCards\Card\TextFitter;
use Phil\SocialCards\Tests\Support\BoxFont;
use Phil\SocialCards\Tests\Support\Directories;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The cut of a title measured by GD in a font that has the ellipsis and the guillemets: the box font the tests write,
 * since the fixture font covers printable ASCII and `é` only.
 */
#[CoversClass(TextFitter::class)]
#[CoversClass(GdTextMeasure::class)]
#[CoversClass(GdCardRenderer::class)]
final class EllipsisTest extends TestCase
{
    private string $directory;

    private CardFont $font;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = Directories::make('box-font');
        $this->font = CardFont::fromFile(BoxFont::write($this->directory));
    }

    #[Override]
    protected function tearDown(): void
    {
        Directories::remove($this->directory);
    }

    public function testTheFixtureFontLacksTheEllipsis(): void
    {
        $measure = new GdTextMeasure();
        $fixture = CardFont::fromFile(__DIR__.'/../../Fixtures/card-font.ttf');

        self::assertSame($measure->width('☃', $fixture, 100), $measure->width('…', $fixture, 100), 'Both are drawn as the box of .notdef.');
        self::assertSame($measure->width('☃', $fixture, 100), $measure->width('«', $fixture, 100));
    }

    public function testTheBoxFontHasTheEllipsisAndTheGuillemets(): void
    {
        $measure = new GdTextMeasure();

        self::assertSame(BoxFont::FAMILY, $this->font->family);
        self::assertGreaterThan(2 * $measure->width('a', $this->font, 100), $measure->width('…', $this->font, 100), 'The ellipsis is wider than two letters.');
        self::assertGreaterThan($measure->width('a', $this->font, 100), $measure->width('«', $this->font, 100));
        self::assertNotSame($measure->width('☃', $this->font, 100), $measure->width('…', $this->font, 100));
    }

    public function testATitleIsCutWhereTheEllipsisHolds(): void
    {
        $measure = new GdTextMeasure();
        $fitter = new TextFitter($measure);
        $title = '« Une aventure bien trop longue pour une seule ligne de la carte »';

        $wide = $fitter->fit($title, 'fr', $this->font, 600, 1, 60, 60);
        self::assertSame([['« Une aventure bien…'], true], [$wide->lines, $wide->truncated]);

        // « Une aventure » holds in 440 pixels, but not with the ellipsis after it: its last letter makes room.
        self::assertLessThanOrEqual(440, $measure->width('« Une aventure', $this->font, 60));
        self::assertGreaterThan(440, $measure->width('« Une aventure…', $this->font, 60));
        $narrow = $fitter->fit($title, 'fr', $this->font, 440, 1, 60, 60);
        self::assertSame(['« Une aventur…'], $narrow->lines);
        self::assertLessThanOrEqual(440, $measure->width($narrow->lines[0], $this->font, 60));
    }

    public function testACardDrawsTheGuillemetsAndTheEllipsis(): void
    {
        $png = new GdCardRenderer()->render(new CardTemplate($this->font), new CardContent('« '.str_repeat('Une aventure sans fin ', 12).'»', '… et la suite', 'fr'));

        self::assertStringStartsWith("\x89PNG", $png);
        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);
        self::assertSame([CardTemplate::WIDTH, CardTemplate::HEIGHT], [imagesx($image), imagesy($image)]);
    }
}
