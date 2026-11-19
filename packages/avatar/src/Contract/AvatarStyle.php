<?php

declare(strict_types=1);

namespace Phil\Avatar\Contract;

use Phil\Avatar\Canvas;
use Phil\Avatar\Random;

/**
 * A way of drawing avatars, under a name. A style draws on the canvas with the choices of the random stream, which the
 * seed and the style's name fix: the same seed draws the same avatar, and another style draws another one.
 */
interface AvatarStyle
{
    /**
     * The name the options ask for: 1 to 32 lowercase letters, digits or "-".
     */
    public function name(): string;

    public function draw(Canvas $canvas, Random $random): void;
}
