<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use GdImage;
use Phil\SocialCards\Contract\CardRenderer;
use Phil\SocialCards\Contract\TextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;

/**
 * Draws cards with GD and FreeType: the background colour, the background image scaled to cover the card and cropped
 * around its centre, the title fitted at the top-left within the margin, the subtitle below it — shortened by lines, or
 * left out, when the logo needs the room —, the logo in the bottom-left corner, shrunk in its proportions to 360×96
 * pixels at most, and the accent as a band of 12 pixels along the bottom edge. An image so thin that it would be shrunk
 * below one pixel is left out. The PNG is written at the highest compression, opaque.
 *
 * GD draws each character as the font gives it, from left to right: the scripts that need shaping — Arabic, Hebrew, the
 * scripts of India — are drawn as separate letters.
 */
final readonly class GdCardRenderer implements CardRenderer
{
    public const string VERSION = 'gd-1';

    public const int ACCENT_HEIGHT = 12;

    public const int LOGO_WIDTH = 360;

    public const int LOGO_HEIGHT = 96;

    /** The room between the title and the subtitle, and between the text and the logo. */
    public const int GAP = 32;

    private TextFitter $fitter;

    public function __construct(TextMeasure $measure = new GdTextMeasure())
    {
        $this->fitter = new TextFitter($measure);
    }

    public function version(): string
    {
        return self::VERSION.':'.\GD_VERSION;
    }

    public function render(CardTemplate $template, CardContent $content): string
    {
        /** @var GdImage $image a true-colour image of a fixed, valid size */
        $image = imagecreatetruecolor(CardTemplate::WIDTH, CardTemplate::HEIGHT);
        $background = $template->background;
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $background->red, $background->green, $background->blue));

        if ($template->backgroundImage instanceof CardImage) {
            $this->cover($image, $template->backgroundImage);
        }

        $bottom = CardTemplate::HEIGHT - $template->padding;

        if ($template->logo instanceof CardImage) {
            $bottom = $this->logo($image, $template->logo, $template->padding) - self::GAP;
        }

        $this->text($image, $template, $content, $bottom);

        if ($template->accent instanceof Color) {
            imagefilledrectangle($image, 0, CardTemplate::HEIGHT - self::ACCENT_HEIGHT, CardTemplate::WIDTH, CardTemplate::HEIGHT, $this->color($image, $template->accent));
        }

        $stream = fopen('php://memory', 'w+');

        if (false === $stream || !imagepng($image, $stream, 9)) {
            throw CardRenderingFailed::because('GD cannot write the card as a PNG.');
        }

        rewind($stream);
        $png = (string) stream_get_contents($stream);
        fclose($stream);

        return $png;
    }

    private function cover(GdImage $card, CardImage $image): void
    {
        $source = $this->open($image, 'background image');
        // The largest part of the image in the proportions of the card: its whole height when it is wider, its whole width
        // when it is taller.
        $width = min($image->width, intdiv($image->height * CardTemplate::WIDTH, CardTemplate::HEIGHT));
        $height = min($image->height, intdiv($image->width * CardTemplate::HEIGHT, CardTemplate::WIDTH));
        imagecopyresampled($card, $source, 0, 0, intdiv($image->width - $width, 2), intdiv($image->height - $height, 2), CardTemplate::WIDTH, CardTemplate::HEIGHT, $width, $height);
    }

    /**
     * Draws the logo in the bottom-left corner and returns where its top is.
     */
    private function logo(GdImage $card, CardImage $logo, int $padding): int
    {
        $source = $this->open($logo, 'logo');
        // Never enlarged, shrunk in its proportions to the box when larger.
        $width = min($logo->width, self::LOGO_WIDTH, intdiv($logo->width * self::LOGO_HEIGHT, $logo->height));
        $height = min($logo->height, self::LOGO_HEIGHT, intdiv($logo->height * self::LOGO_WIDTH, $logo->width));
        $top = CardTemplate::HEIGHT - $padding - $height;
        imagecopyresampled($card, $source, $padding, $top, 0, 0, $width, $height, $logo->width, $logo->height);

        return $top;
    }

    private function text(GdImage $card, CardTemplate $template, CardContent $content, int $bottom): void
    {
        $width = CardTemplate::WIDTH - 2 * $template->padding;
        $title = $this->fitter->fit($content->title, $content->locale, $template->titleFont, $width, $template->titleMaxLines, $template->titleMaxSize, $template->titleMinSize);
        $top = $this->lines($card, $title, $template->titleFont, $template->titleColor, $template->padding, $template->padding);

        if (null === $content->subtitle) {
            return;
        }

        $room = intdiv($bottom - $top - self::GAP, FittedText::lineHeight($template->subtitleSize));
        $lines = min($template->subtitleMaxLines, $room);
        $subtitle = $this->fitter->fit($content->subtitle, $content->locale, $template->subtitleFont(), $width, $lines, $template->subtitleSize, $template->subtitleSize);
        $this->lines($card, $subtitle, $template->subtitleFont(), $template->subtitleColor, $template->padding, $top + self::GAP);
    }

    /**
     * Draws the lines from a top edge and returns where the last one ends.
     */
    private function lines(GdImage $card, FittedText $text, CardFont $font, Color $color, int $left, int $top): int
    {
        $ink = $this->color($card, $color);
        $lineHeight = FittedText::lineHeight($text->size);
        $ascent = (int) round($text->size * 0.93);

        foreach ($text->lines as $index => $line) {
            if (false === @imagettftext($card, GdTextMeasure::points($text->size), 0, $left, $top + $index * $lineHeight + $ascent, $ink, $font->path, $line)) {
                throw CardRenderingFailed::because(\sprintf('GD cannot draw text in the font "%s".', $font->path));
            }
        }

        return $top + $text->height();
    }

    private function color(GdImage $card, Color $color): int
    {
        return (int) imagecolorallocatealpha($card, $color->red, $color->green, $color->blue, $color->gdAlpha());
    }

    private function open(CardImage $image, string $name): GdImage
    {
        $gd = @imagecreatefromstring($image->bytes);

        if (false === $gd) {
            throw CardRenderingFailed::because(\sprintf('GD cannot read the %s.', $name));
        }

        return $gd;
    }
}
