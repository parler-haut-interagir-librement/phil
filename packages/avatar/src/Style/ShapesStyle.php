<?php

declare(strict_types=1);

namespace Phil\Avatar\Style;

use Override;
use Phil\Avatar\Canvas;
use Phil\Avatar\Color;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

/**
 * Geometric shapes on a background: three circles, squares or triangles, of the palette's other colors, placed, sized
 * and turned by the seed.
 */
final readonly class ShapesStyle implements AvatarStyle
{
    public const string NAME = 'shapes';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function draw(Canvas $canvas, Random $random): void
    {
        $backgrounds = $canvas->palette->backgrounds;
        $background = $random->pick($backgrounds);
        $canvas->background($background);
        $others = array_values(array_filter($backgrounds, static fn (Color $color): bool => $color->toHex() !== $background->toHex()));
        $fills = [] === $others ? [$canvas->palette->inkFor($background)] : $others;

        for ($shape = 0; $shape < 3; ++$shape) {
            $fill = $random->pick($fills);
            $x = $random->int(10, 90);
            $y = $random->int(10, 90);
            $size = $random->int(18, 46);

            match ($random->int(0, 2)) {
                0 => $canvas->circle($x, $y, $size / 2, $fill),
                1 => $canvas->rectangle($x - $size / 2, $y - $size / 2, $size, $size, $fill, (float) $random->int(0, 89)),
                default => $canvas->polygon($this->triangle($x, $y, $size / 2, $random->int(0, 119)), $fill),
            };
        }
    }

    /**
     * The corners of an equilateral triangle around a centre, turned by a number of degrees.
     *
     * @return non-empty-list<array{float, float}>
     */
    private function triangle(float $x, float $y, float $radius, int $degrees): array
    {
        $corners = [];

        foreach ([0, 120, 240] as $corner) {
            $angle = deg2rad($corner + $degrees - 90);
            $corners[] = [$x + $radius * cos($angle), $y + $radius * sin($angle)];
        }

        return $corners;
    }
}
