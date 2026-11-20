<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardImage;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Card\ImagickCardRenderer;
use Phil\SocialCards\Card\ImagickTextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;
use Phil\SocialCards\Tests\Support\BoxFont;
use Phil\SocialCards\Tests\Support\Directories;
use Phil\SocialCards\Tests\Support\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * The cards ImageMagick draws, in the box font of the tests: each character a filled box, so that where the ink falls
 * is known whatever the version of ImageMagick, Hebrew included.
 */
#[CoversClass(ImagickCardRenderer::class)]
#[CoversClass(ImagickTextMeasure::class)]
#[RequiresPhpExtension('imagick')]
final class ImagickCardRendererTest extends TestCase
{
    private const int INK = 0x111827;

    private string $directory;

    private CardFont $font;

    protected function setUp(): void
    {
        $this->directory = Directories::make('imagick');
        $this->font = CardFont::fromFile(BoxFont::write($this->directory));
    }

    protected function tearDown(): void
    {
        Directories::remove($this->directory);
    }

    public function testACardIsAnOpaquePngOfTheSizeOfOpenGraph(): void
    {
        $png = new ImagickCardRenderer()->render(new CardTemplate($this->font, background: new Color(0xFE, 0xF3, 0xC7)), new CardContent('Hello'));

        self::assertSame([1200, 630, \IMAGETYPE_PNG], \array_slice((array) getimagesizefromstring($png), 0, 3));
        self::assertSame(0xFEF3C7, Images::pixel($png, 0, 0));
        self::assertSame(0xFEF3C7, Images::pixel($png, 1199, 629));
        self::assertGreaterThan(0, $this->ink($png, 80, 80, 400, 160), 'The title is drawn at the top-left, within the margin.');
        self::assertSame(0, $this->ink($png, 0, 0, 78, 630), 'Nothing is drawn in the left margin.');
        self::assertSame(0, $this->ink($png, 0, 0, 1200, 78), 'Nothing is drawn in the top margin.');
        self::assertStringNotContainsString('tRNS', $png);
        self::assertStringNotContainsString('tIME', $png);
        self::assertStringNotContainsString('date:', $png);
    }

    public function testTheSameCardTwiceIsTheSameBytes(): void
    {
        $template = new CardTemplate($this->font, accent: new Color(0xE1, 0x1D, 0x48));
        $content = new CardContent('The same title, drawn twice', 'And its subtitle');

        self::assertSame(new ImagickCardRenderer()->render($template, $content), new ImagickCardRenderer()->render($template, $content));
        self::assertNotSame(new ImagickCardRenderer()->render($template, $content), new ImagickCardRenderer()->render($template, new CardContent('Another title')));
        self::assertMatchesRegularExpression('/^imagick-1:ImageMagick .+:(raqm|plain)$/', new ImagickCardRenderer()->version());
        self::assertSame(ImagickCardRenderer::shapes(), str_ends_with(new ImagickCardRenderer()->version(), ':raqm'));
    }

    public function testTheAccentAndTheSubtitle(): void
    {
        $template = new CardTemplate($this->font, accent: new Color(0xE1, 0x1D, 0x48));
        $png = new ImagickCardRenderer()->render($template, new CardContent('Title', 'Subtitle'));

        self::assertSame(0xE11D48, Images::pixel($png, 0, 629));
        self::assertSame(0xE11D48, Images::pixel($png, 1199, 618));
        self::assertSame(0xFFFFFF, Images::pixel($png, 600, 616), 'The band is 12 pixels high.');
        // The title line is 80 pixels high at 64 pixels, the gap 32: the subtitle starts at 192.
        self::assertGreaterThan(0, Images::count($png, 0x4B5563, 80, 192, 400, 232));
    }

    public function testATextFromRightToLeftIsAlignedOnTheRightMargin(): void
    {
        $png = new ImagickCardRenderer()->render(new CardTemplate($this->font), new CardContent('שלום'));

        self::assertGreaterThan(0, $this->ink($png, 900, 80, 1120, 160), 'The title ends on the right margin.');
        self::assertSame(0, $this->ink($png, 0, 0, 800, 630), 'Nothing is drawn on the left.');
        self::assertSame(0, $this->ink($png, 1122, 0, 1200, 630), 'Nothing is drawn in the right margin.');
    }

    public function testTheBackgroundImageAndTheLogo(): void
    {
        $template = new CardTemplate(
            $this->font,
            backgroundImage: CardImage::fromBytes(Images::png(40, 30, 0x0000FF)),
            logo: CardImage::fromBytes(Images::png(720, 96, 0xFF0000)),
        );
        $png = new ImagickCardRenderer()->render($template, new CardContent('T'));

        self::assertSame(0x0000FF, Images::pixel($png, 1199, 0));
        // 720×96 shrinks to 360×48, at the bottom-left within the margin of 80.
        self::assertSame(0xFF0000, Images::pixel($png, 200, 525));
        self::assertSame(0x0000FF, Images::pixel($png, 500, 525));
    }

    public function testAnImageItCannotReadFails(): void
    {
        // A PNG header on a truncated file: its size reads, its pixels do not.
        $template = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::pngHeader(10, 10)));

        $this->expectException(CardRenderingFailed::class);
        new ImagickCardRenderer()->render($template, new CardContent('T'));
    }

    public function testTheMeasureIsThatOfTheDrawing(): void
    {
        $measure = new ImagickTextMeasure();

        self::assertSame(0, $measure->width('', $this->font, 64));
        self::assertEqualsWithDelta(BoxFont::width('Hello') * 64 / 1000, $measure->width('Hello', $this->font, 64), 2);
    }

    private function ink(string $png, int $left, int $top, int $right, int $bottom): int
    {
        return Images::count($png, self::INK, $left, $top, $right, $bottom);
    }
}
