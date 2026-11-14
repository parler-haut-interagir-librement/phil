<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use ImagickPixel;
use ImagickPixelException;
use Phil\SocialCards\Contract\CardRenderer;
use Phil\SocialCards\Contract\TextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;

/**
 * Draws cards with ImageMagick, through `ext-imagick`, in the layout of `GdCardRenderer`: the background colour, the
 * background image scaled to cover the card and cropped around its centre, the title fitted at the top within the
 * margin, the subtitle below it — shortened by lines, or left out, when the logo needs the room —, the logo in the
 * bottom-left corner, shrunk in its proportions to 360×96 pixels at most, and the accent as a band of 12 pixels along
 * the bottom edge. The PNG is opaque, without the dates ImageMagick writes, at the highest compression.
 *
 * When ImageMagick is built with libraqm, as `shapes()` tells, the text is shaped and laid out in its direction: the
 * letters of Arabic join, Hebrew and Arabic run from right to left, and the scripts of India place their vowel signs and
 * conjuncts, which GD draws as separate letters from left to right. A title or subtitle whose base direction is from
 * right to left is aligned on the right margin. Without libraqm, ImageMagick draws as GD does.
 */
final readonly class ImagickCardRenderer implements CardRenderer
{
    public const string VERSION = 'imagick-1';

    /** The coder of each image a template takes, by the first bytes of its file. */
    private const array CODERS = ["\x89PNG" => 'png', "\xFF\xD8\xFF" => 'jpeg', 'GIF8' => 'gif', 'RIFF' => 'webp'];

    private TextFitter $fitter;

    public function __construct(
        private TextMeasure $measure = new ImagickTextMeasure(),
    ) {
        $this->fitter = new TextFitter($measure);
    }

    /**
     * Whether the ImageMagick of `ext-imagick` shapes text with libraqm.
     */
    public static function shapes(): bool
    {
        $options = Imagick::getConfigureOptions();

        return 1 === preg_match('/\braqm\b/i', ($options['DELEGATES'] ?? '').' '.($options['FEATURES'] ?? ''));
    }

    public function version(): string
    {
        $version = Imagick::getVersion();

        return self::VERSION.':'.$version['versionString'].':'.(self::shapes() ? 'raqm' : 'plain');
    }

    public function render(CardTemplate $template, CardContent $content): string
    {
        try {
            $card = new Imagick();
            $background = $template->background;
            $card->newImage(CardTemplate::WIDTH, CardTemplate::HEIGHT, new ImagickPixel(\sprintf('rgb(%d,%d,%d)', $background->red, $background->green, $background->blue)), 'png');

            if ($template->backgroundImage instanceof CardImage) {
                $this->cover($card, $template->backgroundImage);
            }

            $bottom = CardTemplate::HEIGHT - $template->padding;

            if ($template->logo instanceof CardImage) {
                $bottom = $this->logo($card, $template->logo, $template->padding) - GdCardRenderer::GAP;
            }

            $this->text($card, $template, $content, $bottom);

            if ($template->accent instanceof Color) {
                $band = new ImagickDraw();
                $band->setFillColor($this->pixel($template->accent));
                $band->rectangle(0, CardTemplate::HEIGHT - GdCardRenderer::ACCENT_HEIGHT, CardTemplate::WIDTH - 1, CardTemplate::HEIGHT - 1);
                $card->drawImage($band);
            }

            return $this->png($card);
        } catch (ImagickException|ImagickDrawException|ImagickPixelException $exception) {
            throw CardRenderingFailed::because(\sprintf('ImageMagick cannot draw the card: %s', $exception->getMessage()));
        }
    }

    private function cover(Imagick $card, CardImage $image): void
    {
        $width = min($image->width, intdiv($image->height * CardTemplate::WIDTH, CardTemplate::HEIGHT));
        $height = min($image->height, intdiv($image->width * CardTemplate::HEIGHT, CardTemplate::WIDTH));

        if ($width < 1 || $height < 1) {
            return;
        }

        $source = $this->open($image);
        $source->cropImage($width, $height, intdiv($image->width - $width, 2), intdiv($image->height - $height, 2));
        $source->setImagePage(0, 0, 0, 0);
        $source->resizeImage(CardTemplate::WIDTH, CardTemplate::HEIGHT, Imagick::FILTER_TRIANGLE, 1);

        $card->compositeImage($source, Imagick::COMPOSITE_OVER, 0, 0);
    }

    /**
     * Draws the logo in the bottom-left corner and returns where its top is.
     */
    private function logo(Imagick $card, CardImage $logo, int $padding): int
    {
        $width = min($logo->width, GdCardRenderer::LOGO_WIDTH, intdiv($logo->width * GdCardRenderer::LOGO_HEIGHT, $logo->height));
        $height = min($logo->height, GdCardRenderer::LOGO_HEIGHT, intdiv($logo->height * GdCardRenderer::LOGO_WIDTH, $logo->width));
        $top = CardTemplate::HEIGHT - $padding - $height;

        if ($width >= 1 && $height >= 1) {
            $source = $this->open($logo);
            $source->resizeImage($width, $height, Imagick::FILTER_TRIANGLE, 1);
            $card->compositeImage($source, Imagick::COMPOSITE_OVER, $padding, $top);
        }

        return $top;
    }

    private function text(Imagick $card, CardTemplate $template, CardContent $content, int $bottom): void
    {
        $width = CardTemplate::WIDTH - 2 * $template->padding;
        $title = $this->fitter->fit($content->title, $content->locale, $template->titleFont, $width, $template->titleMaxLines, $template->titleMaxSize, $template->titleMinSize);
        $top = $this->lines($card, $title, $template->titleFont, $template->titleColor, $template, TextDirection::of($content->title), $template->padding);

        if (null === $content->subtitle) {
            return;
        }

        $room = intdiv($bottom - $top - GdCardRenderer::GAP, FittedText::lineHeight($template->subtitleSize));
        $lines = min($template->subtitleMaxLines, $room);
        $subtitle = $this->fitter->fit($content->subtitle, $content->locale, $template->subtitleFont(), $width, $lines, $template->subtitleSize, $template->subtitleSize);
        $this->lines($card, $subtitle, $template->subtitleFont(), $template->subtitleColor, $template, TextDirection::of($content->subtitle), $top + GdCardRenderer::GAP);
    }

    /**
     * Draws the lines from a top edge, on the left margin or, from right to left, on the right one, and returns where
     * the last one ends.
     */
    private function lines(Imagick $card, FittedText $text, CardFont $font, Color $color, CardTemplate $template, TextDirection $direction, int $top): int
    {
        $draw = ImagickTextMeasure::draw($font, $text->size);
        $draw->setFillColor($this->pixel($color));

        $lineHeight = FittedText::lineHeight($text->size);
        $ascent = (int) round($text->size * 0.93);

        foreach ($text->lines as $index => $line) {
            $left = TextDirection::RightToLeft === $direction
                ? CardTemplate::WIDTH - $template->padding - $this->measure->width($line, $font, $text->size)
                : $template->padding;
            $card->annotateImage($draw, $left, $top + $index * $lineHeight + $ascent, 0, $line);
        }

        return $top + $text->height();
    }

    private function pixel(Color $color): ImagickPixel
    {
        return new ImagickPixel(\sprintf('rgba(%d,%d,%d,%F)', $color->red, $color->green, $color->blue, $color->alpha / 255));
    }

    /**
     * Reads an image with the coder of its type alone: a file is never read as another format, an SVG or a script of
     * ImageMagick among them.
     */
    private function open(CardImage $image): Imagick
    {
        $coder = null;

        foreach (self::CODERS as $magic => $name) {
            if (str_starts_with($image->bytes, $magic)) {
                $coder = $name;
            }
        }

        if (null === $coder) {
            throw CardRenderingFailed::because('ImageMagick reads PNG, JPEG, GIF and WebP images only.');
        }

        $source = new Imagick();
        $source->setFormat($coder);
        $source->readImageBlob($image->bytes, $coder.':image');

        return $source;
    }

    private function png(Imagick $card): string
    {
        $card->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $card->stripImage();
        $card->setImageFormat('png');
        $card->setOption('png:color-type', '2');
        $card->setOption('png:bit-depth', '8');
        $card->setOption('png:compression-level', '9');
        $card->setOption('png:exclude-chunks', 'date,time');

        return $card->getImageBlob();
    }
}
