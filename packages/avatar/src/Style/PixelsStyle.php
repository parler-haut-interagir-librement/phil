<?php

declare(strict_types=1);

namespace Phil\Avatar\Style;

use Override;
use Phil\Avatar\Canvas;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

/**
 * A grid of five by five squares, mirrored left to right, in the ink of the higher contrast with the background: the
 * pattern of an identicon, which a face-like symmetry makes easy to tell apart.
 */
final readonly class PixelsStyle implements AvatarStyle
{
    public const string NAME = 'pixels';

    private const int CELLS = 5;

    private const float MARGIN = 12.5;

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function draw(Canvas $canvas, Random $random): void
    {
        $background = $random->pick($canvas->palette->backgrounds);
        $canvas->background($background);
        $ink = $canvas->palette->inkFor($background);
        $cell = (Canvas::SIZE - 2 * self::MARGIN) / self::CELLS;
        $filled = false;

        for ($row = 0; $row < self::CELLS; ++$row) {
            for ($column = 0; $column <= intdiv(self::CELLS, 2); ++$column) {
                if (!$random->chance(50)) {
                    continue;
                }

                $filled = true;
                $canvas->rectangle(self::MARGIN + $column * $cell, self::MARGIN + $row * $cell, $cell, $cell, $ink);

                if ($column < intdiv(self::CELLS, 2)) {
                    $canvas->rectangle(self::MARGIN + (self::CELLS - 1 - $column) * $cell, self::MARGIN + $row * $cell, $cell, $cell, $ink);
                }
            }
        }

        // No avatar is left empty: the centre square stands for a grid the seed left blank.
        if (!$filled) {
            $canvas->rectangle(self::MARGIN + 2 * $cell, self::MARGIN + 2 * $cell, $cell, $cell, $ink);
        }
    }
}
