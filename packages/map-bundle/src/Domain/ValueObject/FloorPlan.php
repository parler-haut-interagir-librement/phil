<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\MapBundle\Domain\Exception\InvalidMap;

/**
 * A floor plan: an image — a trade show's hall, a venue, a campus — of known size in pixels, its booths, and the tiles
 * cut from it when the image is too large to load at once.
 *
 * The plan is drawn on Leaflet's flat reference system, at a scale of 2^-n map units per pixel, n being the smallest
 * that fits the image in 180 units of width and 90 of height: the tiles of zoom n are the image at its own size, its
 * top-left corner at the origin, so that a tile pyramid cut by the tiler lines up with the image and the booths.
 */
final readonly class FloorPlan
{
    public const int MAX_SIDE = 1_000_000;

    public const int MAX_BOOTHS = 2000;

    private const array PLACEHOLDERS = ['{z}', '{x}', '{y}'];

    /**
     * @param list<Booth> $booths
     */
    private function __construct(
        public SiteAddress $image,
        public int $width,
        public int $height,
        public array $booths,
        public ?SiteAddress $tiles,
    ) {
    }

    /**
     * @param iterable<Booth> $booths
     * @param ?SiteAddress    $tiles  the template of the tiles cut from the image, with `{z}`, `{x}` and `{y}`
     *
     * @throws InvalidMap when a side is out of bounds, a booth outside the image or named twice, there are too many, or
     *                    the template lacks a placeholder
     */
    public static function of(SiteAddress $image, int $width, int $height, iterable $booths = [], ?SiteAddress $tiles = null): self
    {
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw InvalidMap::because(\sprintf('A floor plan is 1 to %d pixels wide and high, not %d × %d.', self::MAX_SIDE, $width, $height));
        }

        $list = [];

        foreach ($booths as $booth) {
            if (\count($list) >= self::MAX_BOOTHS) {
                throw InvalidMap::because(\sprintf('A floor plan holds %d booths at most.', self::MAX_BOOTHS));
            }

            if ($booth->x + $booth->width > $width || $booth->y + $booth->height > $height) {
                throw InvalidMap::because(\sprintf('The booth "%s" goes past the edge of the plan.', $booth->id));
            }

            if (isset($list[$booth->id])) {
                throw InvalidMap::because(\sprintf('Two booths of the plan are named "%s".', $booth->id));
            }

            $list[$booth->id] = $booth;
        }

        foreach (self::PLACEHOLDERS as $placeholder) {
            if ($tiles instanceof SiteAddress && !str_contains($tiles->value, $placeholder)) {
                throw InvalidMap::because(\sprintf('The tile template of the plan has no "%s".', $placeholder));
            }
        }

        return new self($image, $width, $height, array_values($list), $tiles);
    }

    /**
     * The zoom at which the plan is shown at its own size: the smallest n with the image in 180 × 90 units at 2^-n.
     */
    public function nativeZoom(): int
    {
        return self::zoomFor($this->width, $this->height);
    }

    /**
     * The zoom at which an image of that size is shown at its own size, as the tiler cuts it.
     */
    public static function zoomFor(int $width, int $height): int
    {
        $zoom = 0;

        while ($width > 180 << $zoom || $height > 90 << $zoom) {
            ++$zoom;
        }

        return $zoom;
    }

    /**
     * Where a pixel of the image lies on the map, latitude then longitude: down the image is south, right is east.
     *
     * @return array{float, float}
     */
    public function position(float $x, float $y): array
    {
        $scale = 2 ** -$this->nativeZoom();

        return [-$y * $scale, $x * $scale];
    }
}
