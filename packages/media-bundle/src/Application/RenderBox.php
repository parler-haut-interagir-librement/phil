<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

/**
 * Where a thumbnail is cut from an image and how large it comes out: `crop` covers the box and keeps the focal
 * point in view, `fit` stays inside it whole; neither enlarges past the original.
 */
final readonly class RenderBox
{
    /**
     * @param int<1, max> $width        the width of the part of the original that is kept
     * @param int<1, max> $height       the height of the part of the original that is kept
     * @param int<1, max> $outputWidth
     * @param int<1, max> $outputHeight
     */
    private function __construct(
        public int $left,
        public int $top,
        public int $width,
        public int $height,
        public int $outputWidth,
        public int $outputHeight,
    ) {
    }

    /**
     * @param int<1, max>         $width      the image's
     * @param int<1, max>         $height     the image's
     * @param int<1, max>         $boxWidth
     * @param int<1, max>         $boxHeight
     * @param string              $mode       `crop` or `fit`
     * @param array{float, float} $focalPoint
     */
    public static function fill(int $width, int $height, int $boxWidth, int $boxHeight, string $mode, array $focalPoint): self
    {
        if ('fit' === $mode) {
            $scale = min($boxWidth / $width, $boxHeight / $height, 1);

            return new self(0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        }

        $cover = max($boxWidth / $width, $boxHeight / $height);
        $keptWidth = min($width, max(1, (int) round($boxWidth / $cover)));
        $keptHeight = min($height, max(1, (int) round($boxHeight / $cover)));

        return new self(
            self::offset($focalPoint[0], $width, $keptWidth),
            self::offset($focalPoint[1], $height, $keptHeight),
            $keptWidth,
            $keptHeight,
            min($boxWidth, $keptWidth),
            min($boxHeight, $keptHeight),
        );
    }

    /**
     * The start of the kept part, centred on the focal point as far as the image allows.
     */
    private static function offset(float $focus, int $length, int $kept): int
    {
        return (int) round(min(max($focus * $length - $kept / 2, 0), $length - $kept));
    }
}
