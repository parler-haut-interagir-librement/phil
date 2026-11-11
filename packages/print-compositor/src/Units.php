<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * Millimetres and points turned into pixels at a resolution.
 */
final class Units
{
    public const float MILLIMETRES_PER_INCH = 25.4;

    public const float POINTS_PER_INCH = 72.0;

    private function __construct()
    {
    }

    /**
     * A length in millimetres, in whole pixels at the given resolution.
     */
    public static function pixels(float $millimetres, int $dpi): int
    {
        return (int) round($millimetres * $dpi / self::MILLIMETRES_PER_INCH);
    }

    /**
     * A size in points, in pixels at the given resolution.
     */
    public static function pointsToPixels(float $points, int $dpi): float
    {
        return $points * $dpi / self::POINTS_PER_INCH;
    }
}
