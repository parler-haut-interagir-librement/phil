<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardImage;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Card\GdTextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;
use Phil\SocialCards\Tests\Support\Images;
use Phil\SocialCards\Tests\Support\MonospaceMeasure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GdCardRenderer::class)]
#[CoversClass(GdTextMeasure::class)]
final class GdCardRendererTest extends TestCase
{
    private const string FONT = __DIR__.'/../../Fixtures/card-font.ttf';

    private const int INK = 0x111827;

    private const int GREY = 0x4B5563;

    private CardFont $font;

    protected function setUp(): void
    {
        $this->font = CardFont::fromFile(self::FONT);
    }

    public function testACardIsAnOpaquePngOfTheSizeOfOpenGraph(): void
    {
        $png = new GdCardRenderer()->render(new CardTemplate($this->font, background: new Color(0xFE, 0xF3, 0xC7)), new CardContent('Hello'));

        self::assertSame([1200, 630, \IMAGETYPE_PNG], \array_slice((array) getimagesizefromstring($png), 0, 3));
        self::assertSame(0xFEF3C7, Images::pixel($png, 0, 0));
        self::assertSame(0xFEF3C7, Images::pixel($png, 1199, 629));
        self::assertSame(0xFEF3C7, Images::pixel($png, 1199, 0));
        self::assertGreaterThan(0, Images::count($png, self::INK, 80, 80, 400, 160), 'The title is drawn at the top-left, within the margin.');
        self::assertSame(0, Images::count($png, self::INK, 0, 0, 80, 630), 'Nothing is drawn in the left margin.');
        self::assertSame(0, Images::count($png, self::INK, 0, 0, 1200, 80), 'Nothing is drawn in the top margin.');
        self::assertSame(0, Images::count($png, self::INK, 0, 165, 1200, 630), 'One line of title only.');
        self::assertStringNotContainsString('tRNS', $png);
    }

    /**
     * Three cards drawn whole, compared pixel by pixel with the cards this GD drew when the test was written: every
     * position, size and colour of the layout is pinned. FreeType and GD place pixels alike within one version of GD.
     */
    public function testThreeCardsDrawnWhole(): void
    {
        $version = gd_info()['GD Version'];

        if ('2.3.3' !== $version) {
            self::markTestSkipped(\sprintf('The reference cards were drawn with GD 2.3.3; this is GD %s.', \is_string($version) ? $version : '?'));
        }

        $renderer = new GdCardRenderer();
        $first = new CardTemplate($this->font, background: new Color(0xFE, 0xF3, 0xC7), accent: new Color(0xE1, 0x1D, 0x48), backgroundImage: CardImage::fromBytes(Images::banded(41, 30, 7, 0x00FF00, 0x223344)), logo: CardImage::fromBytes(Images::png(50, 30, 0xFF0000, true)), padding: 70, titleMaxSize: 60, titleMinSize: 30, subtitleSize: 28);
        $second = new CardTemplate($this->font, backgroundImage: CardImage::fromBytes(Images::banded(31, 45, 21, 0xAA0000, 0x00AA00)), logo: CardImage::fromBytes(Images::png(999, 77, 0x0000FF)), titleMinSize: 50, titleMaxLines: 2, subtitleMaxLines: 3);
        $third = new CardTemplate($this->font, background: new Color(0x33, 0x33, 0x33), backgroundImage: CardImage::fromBytes(Images::png(99, 20, 0x00FFFF, true)), logo: CardImage::fromBytes(Images::png(101, 203, 0xFF00FF)));

        self::assertSame('a34dcdcdf0a60bf98e9503a46924054f', Images::pixelHash($renderer->render($first, new CardContent('A card with its background image, its logo, its accent band and a title on two lines', 'And a subtitle of a line'))));
        self::assertSame('b070c96aad0818f1cdd5f1459c78d677', Images::pixelHash($renderer->render($second, new CardContent(str_repeat('Too long a title to hold in two lines at fifty pixels ', 3), str_repeat('subtitle ', 40)))));
        self::assertSame('6879bbda1aec585dba4f50e65e45c3cc', Images::pixelHash($renderer->render($third, new CardContent('A wide background', 'cropped at its sides'))));
    }

    public function testTheSameCardTwiceIsTheSameBytes(): void
    {
        $template = new CardTemplate($this->font, accent: new Color(0xE1, 0x1D, 0x48));
        $content = new CardContent('The same title, drawn twice', 'And its subtitle');

        self::assertSame(new GdCardRenderer()->render($template, $content), new GdCardRenderer()->render($template, $content));
        self::assertNotSame(new GdCardRenderer()->render($template, $content), new GdCardRenderer()->render($template, new CardContent('Another title')));
        self::assertSame(GdCardRenderer::VERSION.':'.\GD_VERSION, new GdCardRenderer()->version());
        self::assertSame('gd-1', GdCardRenderer::VERSION);
    }

    public function testTheAccentTheSubtitleAndTheTransparency(): void
    {
        $template = new CardTemplate($this->font, titleColor: new Color(0x11, 0x18, 0x27, 0), accent: new Color(0xE1, 0x1D, 0x48));
        $png = new GdCardRenderer()->render($template, new CardContent('Title', 'Subtitle'));

        self::assertSame(0xE11D48, Images::pixel($png, 0, 629));
        self::assertSame(0xE11D48, Images::pixel($png, 1199, 618));
        self::assertSame(0xFFFFFF, Images::pixel($png, 600, 617), 'The band is 12 pixels high.');
        self::assertSame(0, Images::count($png, self::INK, 80, 80, 1120, 160), 'A transparent title leaves the background.');
        // The title line is 80 pixels high at 64 pixels, the gap 32: the subtitle starts at 192.
        self::assertGreaterThan(0, Images::count($png, self::GREY, 80, 192, 400, 232));
        self::assertSame(0, Images::count($png, self::GREY, 0, 0, 1200, 190));
    }

    public function testTheBackgroundImageCoversTheCard(): void
    {
        // 40×30 is wider than 1200×630 is tall: its height is kept, 21 rows at the centre: the 4 rows at the top and
        // the 5 at the bottom are cropped.
        $template = new CardTemplate($this->font, backgroundImage: CardImage::fromBytes(Images::banded(40, 30, 4, 0x00FF00, 0x0000FF)));
        $png = new GdCardRenderer()->render($template, new CardContent('T'));

        self::assertSame(0x0000FF, Images::pixel($png, 0, 0));
        self::assertSame(0x0000FF, Images::pixel($png, 1199, 629));
        self::assertSame(0, Images::count($png, 0x00FF00, 0, 0, 1200, 630));

        $tall = new CardTemplate($this->font, backgroundImage: CardImage::fromBytes(Images::banded(30, 40, 20, 0xFF0000, 0x00FF00)));
        $png = new GdCardRenderer()->render($tall, new CardContent('T'));
        // 30×40: its width is kept, 15 rows at the centre, 12 to 26: 8 red, then 7 green.
        self::assertSame(0xFF0000, Images::pixel($png, 1199, 0));
        self::assertSame(0x00FF00, Images::pixel($png, 0, 629));
        self::assertSame(0xFF0000, Images::pixel($png, 600, 300));
        self::assertSame(0x00FF00, Images::pixel($png, 600, 340));
    }

    public function testTheLogoSitsInTheBottomLeftCorner(): void
    {
        $small = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::png(20, 10, 0xFF0000)));
        $png = new GdCardRenderer()->render($small, new CardContent('T'));

        self::assertSame(0xFF0000, Images::pixel($png, 80, 540));
        self::assertSame(0xFF0000, Images::pixel($png, 99, 549));
        self::assertSame(0xFFFFFF, Images::pixel($png, 79, 545));
        self::assertSame(0xFFFFFF, Images::pixel($png, 100, 545));
        self::assertSame(0xFFFFFF, Images::pixel($png, 85, 539));
        self::assertSame(0xFFFFFF, Images::pixel($png, 85, 550));

        // 720×192 is shrunk by half to 360×96.
        $large = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::png(720, 192, 0x00FF00)));
        $png = new GdCardRenderer()->render($large, new CardContent('T'));
        self::assertSame(0x00FF00, Images::pixel($png, 80, 454));
        self::assertSame(0x00FF00, Images::pixel($png, 439, 549));
        self::assertSame(0xFFFFFF, Images::pixel($png, 440, 500));
        self::assertSame(0xFFFFFF, Images::pixel($png, 100, 453));

        // 1000×20 is shrunk to 360 wide, 7 high.
        $wide = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::png(1000, 20, 0x0000FF)));
        $png = new GdCardRenderer()->render($wide, new CardContent('T'));
        self::assertSame(0x0000FF, Images::pixel($png, 439, 549));
        self::assertSame(0xFFFFFF, Images::pixel($png, 300, 542));

        // A sliver shrunk below one pixel is left out, whichever way it lies.
        foreach ([[4000, 1], [1, 4000]] as [$width, $height]) {
            $sliver = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::png($width, $height, 0x0000FF)));
            self::assertSame(0, Images::count(new GdCardRenderer()->render($sliver, new CardContent('T')), 0x0000FF, 0, 0, 1200, 630));
        }

        // 100×200 is shrunk to 48×96.
        $tall = new CardTemplate($this->font, logo: CardImage::fromBytes(Images::png(100, 200, 0x0000FF)));
        $png = new GdCardRenderer()->render($tall, new CardContent('T'));
        self::assertSame(0x0000FF, Images::pixel($png, 127, 454));
        self::assertSame(0xFFFFFF, Images::pixel($png, 128, 500));

        $transparent = new CardTemplate($this->font, background: new Color(0, 0, 0), logo: CardImage::fromBytes(Images::png(20, 10, 0xFF0000, true)));
        $png = new GdCardRenderer()->render($transparent, new CardContent('T'));
        self::assertSame(0xFF0000, Images::pixel($png, 81, 545));
        self::assertSame(0x000000, Images::pixel($png, 98, 545), 'The transparent half of the logo shows the background.');
    }

    public function testTheSubtitleStopsAtTheBottomMargin(): void
    {
        $renderer = new GdCardRenderer(new MonospaceMeasure());
        // Six lines of title end at 560, below the bottom margin at 550: no room is left for a subtitle.
        $png = $renderer->render(new CardTemplate($this->font, titleMinSize: 64, titleMaxLines: 6), new CardContent(trim(str_repeat('abcdefg ', 24)), 'Subtitle'));

        self::assertSame(0, Images::count($png, self::GREY, 0, 0, 1200, 630));
        self::assertGreaterThan(0, Images::count($png, self::INK, 80, 480, 1120, 560));
    }

    public function testThePngIsWrittenAtTheHighestCompression(): void
    {
        $png = new GdCardRenderer()->render(new CardTemplate($this->font), new CardContent('Compressed'));
        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image, null, 9);

        self::assertSame($png, ob_get_clean());
    }

    public function testTheSubtitleMakesRoomForTheLogo(): void
    {
        $logo = CardImage::fromBytes(Images::png(360, 96, 0x00FF00));
        $title = trim(str_repeat('abcdefg ', 12));
        $measure = new MonospaceMeasure();
        $renderer = new GdCardRenderer($measure);

        // Three lines of title end at 320; with the logo the text ends at 422: 70 pixels after the gap of 32, room for
        // two lines of 30 pixels, one of 40 or 63 or 70, none of 75.
        $twoLines = $renderer->render(new CardTemplate($this->font, logo: $logo, subtitleSize: 24, subtitleMaxLines: 4), new CardContent($title, str_repeat('subtitle ', 30)));
        self::assertGreaterThan(0, Images::count($twoLines, self::GREY, 80, 352, 1120, 382));
        self::assertGreaterThan(0, Images::count($twoLines, self::GREY, 80, 382, 1120, 412));
        self::assertSame(0, Images::count($twoLines, self::GREY, 0, 415, 1200, 630));

        $none = $renderer->render(new CardTemplate($this->font, logo: $logo, subtitleSize: 60), new CardContent($title, 'Subtitle'));
        self::assertSame(0, Images::count($none, self::GREY, 0, 0, 1200, 630), 'No line of subtitle holds above the logo.');

        $one = $renderer->render(new CardTemplate($this->font, logo: $logo, subtitleSize: 50), new CardContent($title, str_repeat('subtitle ', 30)));
        self::assertGreaterThan(0, Images::count($one, self::GREY, 80, 352, 1120, 400));
        self::assertSame(0, Images::count($one, self::GREY, 0, 415, 1200, 630), 'One line of 63 pixels holds, not two.');

        $exact = $renderer->render(new CardTemplate($this->font, logo: $logo, subtitleSize: 56), new CardContent($title, 'Subtitle'));
        self::assertGreaterThan(0, Images::count($exact, self::GREY, 0, 0, 1200, 630), 'A line of 70 pixels fits the 70 left exactly.');
    }

    public function testASubtitleInItsOwnFont(): void
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-render-'.bin2hex(random_bytes(4));
        mkdir($directory);
        copy(self::FONT, $directory.'/subtitle.ttf');
        $subtitleFont = CardFont::fromFile($directory.'/subtitle.ttf');
        unlink($directory.'/subtitle.ttf');

        try {
            new GdCardRenderer(new MonospaceMeasure())->render(new CardTemplate($this->font, $subtitleFont), new CardContent('Title', 'Subtitle'));
            self::fail('The subtitle is drawn in its own font, which is gone.');
        } catch (CardRenderingFailed $cardRenderingFailed) {
            self::assertSame(\sprintf('GD cannot draw text in the font "%s".', $directory.'/subtitle.ttf'), $cardRenderingFailed->getMessage());
        } finally {
            rmdir($directory);
        }
    }

    public function testAMeasureInAFontThatIsGone(): void
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-render-'.bin2hex(random_bytes(4));
        mkdir($directory);
        copy(self::FONT, $directory.'/gone.ttf');
        $font = CardFont::fromFile($directory.'/gone.ttf');
        unlink($directory.'/gone.ttf');
        rmdir($directory);

        self::assertSame(0, new GdTextMeasure()->width('', $font, 20));
        $this->expectException(CardRenderingFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('GD cannot measure text in the font "%s".', $directory.'/gone.ttf'));
        new GdTextMeasure()->width('x', $font, 20);
    }

    public function testTheMeasureOfGd(): void
    {
        $measure = new GdTextMeasure();
        /** @var array{int, int, int, int, int, int, int, int}|false $box */
        $box = imagettfbbox(30.0, 0, self::FONT, 'Hello World');
        self::assertNotFalse($box);

        self::assertSame($box[2] - $box[0], $measure->width('Hello World', $this->font, 40));
        self::assertSame([$box[2], $box[0]], [$box[4], $box[6]], 'Unrotated, the box is square to the line.');
        self::assertGreaterThan($measure->width('Hello', $this->font, 40), $measure->width('Hello World', $this->font, 40));
        self::assertSame(30.0, GdTextMeasure::points(40));
        self::assertSame(0.75, GdTextMeasure::points(1));
    }

    public function testAnImageGdCannotRead(): void
    {
        // A PNG cut after its header: its size is read, its pixels are not.
        $truncated = substr(Images::png(40, 30), 0, 40);
        $this->expectException(CardRenderingFailed::class);
        $this->expectExceptionMessageIsOrContains('GD cannot read the logo.');

        new GdCardRenderer()->render(new CardTemplate($this->font, logo: CardImage::fromBytes($truncated)), new CardContent('T'));
    }

    public function testABackgroundGdCannotRead(): void
    {
        $this->expectExceptionMessageIsOrContains('GD cannot read the background image.');

        new GdCardRenderer()->render(new CardTemplate($this->font, backgroundImage: CardImage::fromBytes(substr(Images::png(40, 30), 0, 40))), new CardContent('T'));
    }
}
