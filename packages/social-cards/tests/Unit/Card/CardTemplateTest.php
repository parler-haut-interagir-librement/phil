<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use ErrorException;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardImage;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\Tests\Support\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(CardTemplate::class)]
#[CoversClass(CardFont::class)]
#[CoversClass(CardImage::class)]
final class CardTemplateTest extends TestCase
{
    private const string FONT = __DIR__.'/../../Fixtures/card-font.ttf';

    public function testAFontIsReadByPhilFonts(): void
    {
        $font = CardFont::fromFile(self::FONT);

        self::assertSame([self::FONT, hash_file('sha256', self::FONT), 'Test'], [$font->path, $font->sha256, $font->family]);
        self::assertNull($font->licence->spdx());
    }

    public function testWhatIsNoFont(): void
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-fonts-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/text.ttf', 'not a font at all, but long enough to be read as one');
        file_put_contents($directory.'/font.woff', $this->woff((string) file_get_contents(self::FONT)));

        foreach ([
            $directory.'/missing.ttf' => 'cannot be read, or is larger than 33554432 bytes',
            $directory => 'cannot be read',
            $directory.'/text.ttf' => 'is not a font: ',
            $directory.'/font.woff' => 'is a woff file; a card is drawn from a TrueType or OpenType file.',
        ] as $path => $message) {
            try {
                CardFont::fromFile($path);
                self::fail(\sprintf('"%s" is refused.', $path));
            } catch (InvalidCardTemplate $invalidCardTemplate) {
                self::assertStringContainsString($message, $invalidCardTemplate->getMessage());
                self::assertSame(str_contains($message, 'is not a font'), $invalidCardTemplate->getPrevious() instanceof Throwable);
                self::assertSame(0, $invalidCardTemplate->getCode());
            }
        }

        // A font of 32 MiB is read — and is no font —; one byte more is not read at all.
        $handle = fopen($directory.'/huge.ttf', 'w');
        self::assertNotFalse($handle);
        ftruncate($handle, CardFont::MAX_BYTES);
        fclose($handle);

        try {
            CardFont::fromFile($directory.'/huge.ttf');
            self::fail('A font of zeros is refused.');
        } catch (InvalidCardTemplate $invalidCardTemplate) {
            self::assertStringContainsString('is not a font', $invalidCardTemplate->getMessage());
        }

        file_put_contents($directory.'/huge.ttf', "\0", \FILE_APPEND);

        try {
            CardFont::fromFile($directory.'/huge.ttf');
            self::fail('A font larger than 32 MiB is refused.');
        } catch (InvalidCardTemplate $invalidCardTemplate) {
            self::assertStringContainsString('cannot be read, or is larger than 33554432 bytes', $invalidCardTemplate->getMessage());
        }

        array_map(unlink(...), [$directory.'/text.ttf', $directory.'/font.woff', $directory.'/huge.ttf']);
        rmdir($directory);
    }

    public function testTheDefaults(): void
    {
        $template = new CardTemplate(CardFont::fromFile(self::FONT));

        self::assertSame([1200, 630], [CardTemplate::WIDTH, CardTemplate::HEIGHT]);
        self::assertSame(['#ffffffff', '#111827ff', '#4b5563ff', null, null, null], [$template->background->hex(), $template->titleColor->hex(), $template->subtitleColor->hex(), $template->accent, $template->backgroundImage, $template->logo]);
        self::assertSame([80, 64, 36, 3, 32, 2], [$template->padding, $template->titleMaxSize, $template->titleMinSize, $template->titleMaxLines, $template->subtitleSize, $template->subtitleMaxLines]);
        self::assertSame($template->titleFont, $template->subtitleFont());
        self::assertEquals($template, CardTemplate::fromArray(['title_font' => self::FONT]));
    }

    public function testFromAnArray(): void
    {
        $directory = $this->images();
        $template = CardTemplate::fromArray([
            'title_font' => self::FONT,
            'subtitle_font' => self::FONT,
            'background' => '#000',
            'title_color' => '#fff',
            'subtitle_color' => '#eee',
            'accent' => '#e11d48',
            'background_image' => $directory.'/background.png',
            'logo' => $directory.'/logo.png',
            'padding' => 0,
            'title_max_size' => 200,
            'title_min_size' => 8,
            'title_max_lines' => 6,
            'subtitle_size' => 120,
            'subtitle_max_lines' => 4,
        ]);

        self::assertSame(['#000000ff', '#ffffffff', '#eeeeeeff', '#e11d48ff'], [$template->background->hex(), $template->titleColor->hex(), $template->subtitleColor->hex(), $template->accent?->hex()]);
        self::assertSame([40, 30, 20, 10], [$template->backgroundImage?->width, $template->backgroundImage?->height, $template->logo?->width, $template->logo?->height]);
        self::assertSame(hash_file('sha256', $directory.'/logo.png'), $template->logo?->sha256);
        self::assertSame((string) file_get_contents($directory.'/logo.png'), $template->logo->bytes);
        self::assertSame([0, 200, 8, 6, 120, 4], [$template->padding, $template->titleMaxSize, $template->titleMinSize, $template->titleMaxLines, $template->subtitleSize, $template->subtitleMaxLines]);
        self::assertNotSame($template->titleFont, $template->subtitleFont());
        self::assertSame($template->subtitleFont, $template->subtitleFont());
    }

    public function testTheFingerprintFollowsEveryLookAndNoPath(): void
    {
        $directory = $this->images();
        $font = CardFont::fromFile(self::FONT);
        copy(self::FONT, $directory.'/copy.ttf');
        $base = new CardTemplate($font);
        $fingerprints = [$base->fingerprint()];
        $variants = [
            new CardTemplate($font, subtitleFont: $this->otherFont($directory)),
            new CardTemplate($this->otherFont($directory), subtitleFont: $font),
            new CardTemplate($font, background: new Color(0, 0, 0)),
            new CardTemplate($font, titleColor: new Color(0, 0, 1)),
            new CardTemplate($font, subtitleColor: new Color(0, 0, 2)),
            new CardTemplate($font, accent: new Color(0, 0, 3)),
            new CardTemplate($font, backgroundImage: CardImage::fromFile($directory.'/background.png')),
            new CardTemplate($font, logo: CardImage::fromFile($directory.'/logo.png')),
            new CardTemplate($font, padding: 81),
            new CardTemplate($font, titleMaxSize: 65),
            new CardTemplate($font, titleMinSize: 37),
            new CardTemplate($font, titleMaxLines: 2),
            new CardTemplate($font, subtitleSize: 33),
            new CardTemplate($font, subtitleMaxLines: 1),
        ];

        foreach ($variants as $variant) {
            $fingerprints[] = $variant->fingerprint();
        }

        self::assertCount(15, array_unique($fingerprints));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $base->fingerprint());
        self::assertSame($base->fingerprint(), new CardTemplate(CardFont::fromFile($directory.'/copy.ttf'))->fingerprint());
        self::assertSame($base->fingerprint(), new CardTemplate($font, subtitleFont: $font)->fingerprint(), 'The subtitle drawn in the title font is the same card.');
        self::assertNotSame(new CardTemplate($font, logo: CardImage::fromFile($directory.'/logo.png'))->fingerprint(), new CardTemplate($font, backgroundImage: CardImage::fromFile($directory.'/logo.png'))->fingerprint());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badTemplates(): iterable
    {
        yield 'no font' => [[], 'A card template needs its "title_font".'];
        yield 'an unknown key' => [['title_font' => self::FONT, 'colour' => '#fff'], 'A card template has no key "colour"; it reads title_font, subtitle_font, background, title_color, subtitle_color, accent, background_image, logo, padding, title_max_size, title_min_size, title_max_lines, subtitle_size, subtitle_max_lines.'];
        yield 'a font that is no string' => [['title_font' => 1], 'The "title_font" of a card template is a string; int is given.'];
        yield 'a padding that is no integer' => [['title_font' => self::FONT, 'padding' => '80'], 'The "padding" of a card template is an integer; string is given.'];
        yield 'a negative padding' => [['title_font' => self::FONT, 'padding' => -1], 'The padding of a card is 0 to 200; -1 is not.'];
        yield 'a wide padding' => [['title_font' => self::FONT, 'padding' => 201], 'The padding of a card is 0 to 200; 201 is not.'];
        yield 'a tiny title' => [['title_font' => self::FONT, 'title_max_size' => 7, 'title_min_size' => 7], 'The largest title size of a card is 8 to 200; 7 is not.'];
        yield 'a huge title' => [['title_font' => self::FONT, 'title_max_size' => 201], 'The largest title size of a card is 8 to 200; 201 is not.'];
        yield 'a tiny smallest title' => [['title_font' => self::FONT, 'title_min_size' => 7], 'The smallest title size of a card is 8 to 64; 7 is not.'];
        yield 'a smallest title above the largest' => [['title_font' => self::FONT, 'title_min_size' => 65], 'The smallest title size of a card is 8 to 64; 65 is not.'];
        yield 'no title line' => [['title_font' => self::FONT, 'title_max_lines' => 0], 'The number of title lines of a card is 1 to 6; 0 is not.'];
        yield 'too many title lines' => [['title_font' => self::FONT, 'title_max_lines' => 7], 'The number of title lines of a card is 1 to 6; 7 is not.'];
        yield 'a tiny subtitle' => [['title_font' => self::FONT, 'subtitle_size' => 7], 'The subtitle size of a card is 8 to 120; 7 is not.'];
        yield 'a huge subtitle' => [['title_font' => self::FONT, 'subtitle_size' => 121], 'The subtitle size of a card is 8 to 120; 121 is not.'];
        yield 'no subtitle line' => [['title_font' => self::FONT, 'subtitle_max_lines' => 0], 'The number of subtitle lines of a card is 1 to 4; 0 is not.'];
        yield 'too many subtitle lines' => [['title_font' => self::FONT, 'subtitle_max_lines' => 5], 'The number of subtitle lines of a card is 1 to 4; 5 is not.'];
        yield 'a bad colour' => [['title_font' => self::FONT, 'accent' => 'red'], '"red" is not a colour'];
        yield 'a missing logo' => [['title_font' => self::FONT, 'logo' => '/nowhere/logo.png'], 'The image "/nowhere/logo.png" cannot be read, or is larger than 10485760 bytes.'];
        yield 'a font that is a text' => [['title_font' => __FILE__], 'is not a font'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badTemplates')]
    public function testABadTemplateIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidCardTemplate::class);
        $this->expectExceptionMessageIsOrContains($message);

        CardTemplate::fromArray($config);
    }

    public function testAMissingImageRaisesNoWarning(): void
    {
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        try {
            foreach (['/nowhere/logo.png', __DIR__] as $path) {
                try {
                    CardImage::fromFile($path);
                    self::fail('A missing image, or a directory, is refused.');
                } catch (InvalidCardTemplate $invalidCardTemplate) {
                    self::assertStringContainsString('cannot be read', $invalidCardTemplate->getMessage());
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testWhatIsNoImage(): void
    {
        $gif = imagecreatetruecolor(2, 3);
        self::assertNotFalse($gif);
        ob_start();
        imagegif($gif);
        $gifBytes = (string) ob_get_clean();
        $wide = Images::png(8001, 1);

        self::assertSame([2, 3], [CardImage::fromBytes($gifBytes)->width, CardImage::fromBytes($gifBytes)->height]);
        self::assertSame(8000, CardImage::fromBytes(Images::png(8000, 1))->width);
        self::assertSame(8000, CardImage::fromBytes(Images::png(1, 8000))->height);
        self::assertSame([4096, 4096], [CardImage::fromBytes(Images::pngHeader(4096, 4096))->width, CardImage::fromBytes(Images::pngHeader(4096, 4096))->height]);
        self::assertSame(2097, CardImage::fromBytes(Images::pngHeader(8000, 2097))->height);

        foreach ([[4097, 4096], [4096, 4097], [8000, 2098], [8000, 8000]] as [$width, $height]) {
            try {
                CardImage::fromBytes(Images::pngHeader($width, $height), 'background');
                self::fail(\sprintf('An image of %d×%d pixels is refused.', $width, $height));
            } catch (InvalidCardTemplate $invalidCardTemplate) {
                self::assertSame(\sprintf('The background is %d×%d pixels; a card draws images of 1 to 8000 pixels a side and 16777216 pixels in all.', $width, $height), $invalidCardTemplate->getMessage());
            }
        }

        $png = Images::png(2, 2);
        $largest = $png.str_repeat("\0", CardImage::MAX_BYTES - \strlen($png));
        self::assertSame(2, CardImage::fromBytes($largest)->width, 'An image of 10 MiB is read.');
        $directory = $this->images();
        file_put_contents($directory.'/largest.png', $largest);
        self::assertSame(2, CardImage::fromFile($directory.'/largest.png')->width);
        file_put_contents($directory.'/larger.png', $largest."\0");

        try {
            CardImage::fromFile($directory.'/larger.png');
            self::fail('A file larger than 10 MiB is refused.');
        } catch (InvalidCardTemplate $invalidCardTemplate) {
            self::assertStringContainsString('cannot be read, or is larger than 10485760 bytes', $invalidCardTemplate->getMessage());
        }

        try {
            CardImage::fromBytes($largest."\0");
            self::fail('Bytes beyond 10 MiB are refused.');
        } catch (InvalidCardTemplate $invalidCardTemplate) {
            self::assertStringContainsString('is not a PNG, JPEG, GIF or WebP image of 10485760 bytes at most', $invalidCardTemplate->getMessage());
        }

        foreach ([
            'not an image' => 'The logo is not a PNG, JPEG, GIF or WebP image of 10485760 bytes at most.',
            '<svg xmlns="http://www.w3.org/2000/svg"/>' => 'is not a PNG',
            "BM\x3A\0\0\0\0\0\0\0\x36\0\0\0\x28\0\0\0\1\0\0\0\1\0\0\0\1\0\x18\0\0\0\0\0\4\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0" => 'is not a PNG',
            $wide => 'The logo is 8001×1 pixels; a card draws images of 1 to 8000 pixels a side.',
            Images::png(1, 8001) => 'is 1×8001 pixels',
            str_repeat('a', 10 * 1024 * 1024 + 1) => 'is not a PNG',
        ] as $bytes => $message) {
            try {
                CardImage::fromBytes($bytes, 'logo');
                self::fail('The image is refused.');
            } catch (InvalidCardTemplate $invalidCardTemplate) {
                self::assertStringContainsString($message, $invalidCardTemplate->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('The image is not a PNG');
        CardImage::fromBytes('x');
    }

    private function images(): string
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-images-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/background.png', Images::png(40, 30));
        file_put_contents($directory.'/logo.png', Images::png(20, 10, 0xFF0000));

        return $directory;
    }

    /**
     * The fixture font with one byte of its copyright changed — another file, the same face.
     */
    private function otherFont(string $directory): CardFont
    {
        $bytes = (string) file_get_contents(self::FONT);
        file_put_contents($directory.'/other.ttf', $bytes."\0\0\0\0");

        return CardFont::fromFile($directory.'/other.ttf');
    }

    /**
     * The font in a WOFF container, its tables stored as they are.
     */
    private function woff(string $sfnt): string
    {
        /** @var array{1: int} $header */
        $header = unpack('n', $sfnt, 4);
        $count = $header[1];
        $directory = '';
        $body = '';
        $offset = 44 + 20 * $count;

        for ($index = 0; $index < $count; ++$index) {
            /** @var array{tag: string, checksum: int, start: int, length: int} $record */
            $record = unpack('a4tag/Nchecksum/Nstart/Nlength', substr($sfnt, 12 + 16 * $index, 16));
            $data = substr($sfnt, $record['start'], $record['length']);
            $directory .= $record['tag'].pack('NNNN', $offset + \strlen($body), $record['length'], $record['length'], $record['checksum']);
            $body .= str_pad($data, (int) (ceil($record['length'] / 4) * 4), "\0");
        }

        $total = 44 + \strlen($directory) + \strlen($body);

        return 'wOFF'.substr($sfnt, 0, 4).pack('NnnNnnNNNNN', $total, $count, 0, \strlen($sfnt), 1, 0, 0, 0, 0, 0, 0).$directory.$body;
    }
}
