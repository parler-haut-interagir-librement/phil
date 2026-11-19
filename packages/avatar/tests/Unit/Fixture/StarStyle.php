<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit\Fixture;

use Override;
use Phil\Avatar\Canvas;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

/**
 * A style of an application: one circle on the first background, under a name of its own.
 */
final readonly class StarStyle implements AvatarStyle
{
    public function __construct(
        private string $name = 'star',
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function draw(Canvas $canvas, Random $random): void
    {
        $canvas->background($canvas->palette->backgrounds[0]);
        $canvas->circle(50.0, 50.0, (float) $random->int(10, 40), $canvas->palette->inkFor($canvas->palette->backgrounds[0]));
    }
}
