<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use Phil\SocialCards\Contract\TextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;

/**
 * Measures text with ImageMagick, as `ImagickCardRenderer` draws it: shaped by libraqm when ImageMagick is built with it,
 * so that a word of Arabic or Devanagari measures as its joined letters. A size in pixels is the same size in points, at
 * ImageMagick's 72 dots per inch.
 */
final class ImagickTextMeasure implements TextMeasure
{
    public function width(string $text, CardFont $font, int $size): int
    {
        if ('' === $text) {
            return 0;
        }

        try {
            $draw = self::draw($font, $size);
            $metrics = new Imagick()->queryFontMetrics($draw, $text, false);
        } catch (ImagickDrawException|ImagickException) {
            throw CardRenderingFailed::because(\sprintf('ImageMagick cannot measure text in the font "%s".', $font->path));
        }

        return (int) ceil($metrics['textWidth']);
    }

    /**
     * The drawing settings of text in a font at a size in pixels, antialiased, as measured and as drawn.
     */
    public static function draw(CardFont $font, int $size): ImagickDraw
    {
        $draw = new ImagickDraw();
        $draw->setFont($font->path);
        $draw->setFontSize($size);
        $draw->setTextAntialias(true);

        return $draw;
    }
}
