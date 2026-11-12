<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use Phil\MediaBundle\Application\Transform\Geometry;
use Phil\MediaBundle\Application\Transform\Pixels;
use Phil\MediaBundle\Application\Transform\Thumbnail;
use Phil\MediaBundle\Application\Transform\Transformer;
use Phil\MediaBundle\Application\Transform\Widen;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * A variation images are rendered to: its name, the transformations applied one after the other, the quality of the
 * lossy file types, and the narrower widths it is also rendered at, for a `srcset`.
 *
 * The name `original` is kept for the file itself, converted whole to another type.
 */
final readonly class ImageFormat
{
    public const array MODES = Thumbnail::MODES;

    /**
     * @param list<Transformer>  $transformers
     * @param int<1, 100>        $quality
     * @param list<int<1, 8000>> $widths       ascending
     */
    private function __construct(
        public string $name,
        public array $transformers,
        public int $quality,
        public array $widths,
    ) {
    }

    /**
     * A box, cropped around the focal point or fitted whole: a single `thumbnail` transformation.
     *
     * @param list<int> $widths
     */
    public static function of(string $name, int $width, int $height, string $mode = 'crop', int $quality = 80, array $widths = []): self
    {
        return self::transforming($name, [Thumbnail::of($width, $height, $mode)], $quality, $widths);
    }

    /**
     * @param list<Transformer> $transformers
     * @param list<int>         $widths
     */
    public static function transforming(string $name, array $transformers, int $quality = 80, array $widths = []): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $name) || 'original' === $name) {
            throw InvalidMedia::because(\sprintf('A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "%s" is not.', $name));
        }

        return new self($name, $transformers, self::quality($quality), self::widths($widths));
    }

    /**
     * The file itself, untransformed, as converted to another type.
     *
     * @param list<int> $widths
     */
    public static function original(int $quality = 80, array $widths = []): self
    {
        return new self('original', [], self::quality($quality), self::widths($widths));
    }

    /**
     * The same variation, then narrowed to a width it declares; never enlarged.
     */
    public function atWidth(int $width): self
    {
        if (!\in_array($width, $this->widths, true)) {
            throw InvalidMedia::because(\sprintf('The format "%s" is not rendered %d pixels wide.', $this->name, $width));
        }

        return new self($this->name, [...$this->transformers, Widen::of($width)], $this->quality, []);
    }

    /**
     * What an image of this size and focal point becomes.
     */
    public function apply(Geometry $image): Geometry
    {
        foreach ($this->transformers as $transformer) {
            $image = $transformer->step($image)->next($image);
        }

        return $image;
    }

    /**
     * @return int<1, 100>
     */
    private static function quality(int $quality): int
    {
        if ($quality < 1 || $quality > 100) {
            throw InvalidMedia::because('A quality is from 1 to 100.');
        }

        return $quality;
    }

    /**
     * @param list<int> $widths
     *
     * @return list<int<1, 8000>>
     */
    private static function widths(array $widths): array
    {
        $widths = array_unique(array_map(Pixels::check(...), $widths));
        sort($widths);

        return $widths;
    }
}
