<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Internal;

use Closure;
use ErrorException;
use GdImage;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Exception\MissingCapability;
use Phil\PrintCompositor\Fit;
use Phil\PrintCompositor\HorizontalAlign;
use Phil\PrintCompositor\VerticalAlign;
use Throwable;

/**
 * The GD calls the library makes, in one place: blank canvases, images fitted into a box, warnings turned into
 * exceptions, encoding to a string.
 *
 * @internal
 */
final class Gd
{
    /** Fully transparent black, as GD writes it in a true-colour image. */
    public const int TRANSPARENT = 0x7F000000;

    private function __construct()
    {
    }

    /**
     * A true-colour canvas of one colour, fully transparent by default, that keeps its alpha channel when encoded and
     * blends what is drawn on it with what is under it. Drawn over a fully transparent pixel, a colour replaces it.
     */
    public static function canvas(int $width, int $height, int $colour = self::TRANSPARENT): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw InvalidLayout::because(\sprintf('An image has a positive size; %d × %d given.', $width, $height));
        }

        $image = imagecreatetruecolor($width, $height);

        if (false === $image) {
            throw InvalidLayout::because(\sprintf('GD cannot allocate a %d × %d image.', $width, $height));
        }

        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $colour);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * A copy of an image as a true-colour canvas; a fully transparent pixel is copied as transparent black.
     */
    public static function copy(GdImage $source): GdImage
    {
        $copy = self::canvas(imagesx($source), imagesy($source));
        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        return $copy;
    }

    /**
     * The source scaled into a transparent canvas of the given size, its proportions kept.
     */
    public static function fitted(GdImage $source, int $width, int $height, Fit $fit, HorizontalAlign $horizontal, VerticalAlign $vertical): GdImage
    {
        $canvas = self::canvas($width, $height);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if (Fit::Contain === $fit) {
            $scale = min($width / $sourceWidth, $height / $sourceHeight);
            $targetWidth = self::scaled($sourceWidth, $scale);
            $targetHeight = self::scaled($sourceHeight, $scale);
            $x = $horizontal->offset($width, $targetWidth);
            $y = $vertical->offset($height, $targetHeight);
            imagecopyresampled($canvas, $source, $x, $y, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            return $canvas;
        }

        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = self::scaled($width, 1 / $scale);
        $cropHeight = self::scaled($height, 1 / $scale);
        $x = $horizontal->offset($sourceWidth, $cropWidth);
        $y = $vertical->offset($sourceHeight, $cropHeight);
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $width, $height, $cropWidth, $cropHeight);

        return $canvas;
    }

    /**
     * A length in pixels scaled, rounded to the nearest pixel and kept at one pixel at least.
     */
    private static function scaled(int $length, float $scale): int
    {
        return max(1, (int) round($length * $scale));
    }

    /**
     * The image encoded by a GD output function, written to memory rather than to a file.
     *
     * @param Closure(GdImage, resource): bool $write
     */
    public static function encode(GdImage $image, Closure $write): string
    {
        $stream = fopen('php://memory', 'w+');
        \assert(false !== $stream);
        $write($image, $stream);
        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);

        return false === $bytes ? '' : $bytes;
    }

    /**
     * Runs a GD call, the first warning or notice it raises turned into the exception the factory builds from it.
     *
     * @template T
     *
     * @param Closure(): T                       $call
     * @param Closure(ErrorException): Throwable $failure
     *
     * @return T
     */
    public static function guarded(Closure $call, Closure $failure): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, severity: $severity, filename: $file, line: $line);
        });

        try {
            return $call();
        } catch (ErrorException $errorException) {
            throw $failure($errorException);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Fails when GD has no function by that name, naming the feature it belongs to.
     */
    public static function require(string $function, string $feature): void
    {
        if (!\function_exists($function)) {
            throw MissingCapability::because(\sprintf('GD is built without %s support: %s() is missing.', $feature, $function));
        }
    }

    /**
     * Fails on a resolution outside the range the library composes at.
     */
    public static function checkDpi(int $dpi): void
    {
        if ($dpi < 1 || $dpi > 2400) {
            throw InvalidLayout::because(\sprintf('A resolution goes from 1 to 2400 dpi; %d given.', $dpi));
        }
    }
}
