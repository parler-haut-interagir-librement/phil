<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Contract\TextMeasure;
use Phil\SocialCards\Exception\CardRenderingFailed;

/**
 * Measures text with FreeType through GD, as `GdCardRenderer` draws it: a size in pixels is three quarters of it in
 * points, GD's 96 dots per inch.
 */
final class GdTextMeasure implements TextMeasure
{
    public function width(string $text, CardFont $font, int $size): int
    {
        if ('' === $text) {
            return 0;
        }

        /** @var array{int, int, int, int, int, int, int, int}|false $box */
        $box = @imagettfbbox(self::points($size), 0, $font->path, $text);

        if (false === $box) {
            throw CardRenderingFailed::because(\sprintf('GD cannot measure text in the font "%s".', $font->path));
        }

        // Unrotated, the box's right edge is its lower-right x and its left edge its lower-left x.
        return $box[2] - $box[0];
    }

    /**
     * The size GD's FreeType functions take, in points, for a size in pixels.
     */
    public static function points(int $pixels): float
    {
        return $pixels * 0.75;
    }
}
