<?php

declare(strict_types=1);

namespace Phil\Avatar\Style;

use Override;
use Phil\Avatar\Canvas;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

/**
 * The initials of the name in the ink of the higher contrast with a background of the palette — 4.5:1 at least, as
 * the palette ensures. A name without initials draws the shapes style instead.
 */
final readonly class InitialsStyle implements AvatarStyle
{
    public const string NAME = 'initials';

    /** The font size, in units of the canvas, by number of letters. */
    private const array FONT_SIZES = [1 => 50.0, 2 => 40.0, 3 => 32.0];

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function draw(Canvas $canvas, Random $random): void
    {
        $initials = $canvas->initials;

        if ($initials->isEmpty()) {
            new ShapesStyle()->draw($canvas, $random);

            return;
        }

        $background = $random->pick($canvas->palette->backgrounds);
        $canvas->background($background);
        $letters = min(3, max(1, (int) preg_match_all('/\X/u', $initials->letters)));
        $canvas->centredText($initials->letters, $canvas->palette->inkFor($background), self::FONT_SIZES[$letters], $initials->isRightToLeft());
    }
}
