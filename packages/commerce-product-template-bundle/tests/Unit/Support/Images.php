<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

/**
 * Small PNG images of one colour, drawn by GD.
 */
final class Images
{
    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param int<0, 255> $red
     * @param int<0, 255> $green
     * @param int<0, 255> $blue
     */
    public static function png(int $width, int $height, int $red, int $green, int $blue): string
    {
        $image = imagecreatetruecolor($width, $height);
        \assert(false !== $image);
        $colour = imagecolorallocate($image, $red, $green, $blue);
        \assert(false !== $colour);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $colour);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
