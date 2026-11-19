<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Where the tiles of a geographic map come from: an address template with `{z}`, `{x}` and `{y}`, as Leaflet reads it,
 * the attribution its licence requires, and the deepest zoom it serves.
 *
 * The template is read as an address by phil/url, with each placeholder replaced by a number: an address with
 * credentials, an IP address or a host without a dot is refused, `localhost` too unless allowed.
 */
final readonly class TileSource
{
    public const int MAX_ZOOM = 22;

    private const array PLACEHOLDERS = ['{z}', '{x}', '{y}'];

    private function __construct(
        public string $template,
        public string $attribution,
        public int $maxZoom,
        public bool $allowLocalhost,
    ) {
    }

    /**
     * @throws InvalidMap when the template lacks a placeholder or is no address, the attribution is empty or the zoom
     *                    out of 0 to 22
     */
    public static function of(string $template, string $attribution, int $maxZoom = 19, bool $allowLocalhost = false): self
    {
        foreach (self::PLACEHOLDERS as $placeholder) {
            if (!str_contains($template, $placeholder)) {
                throw InvalidMap::because(\sprintf('The tile template "%s" has no "%s".', $template, $placeholder));
            }
        }

        if ('' === trim($attribution)) {
            throw InvalidMap::because('A tile source names its attribution: its licence requires it.');
        }

        if ($maxZoom < 0 || $maxZoom > self::MAX_ZOOM) {
            throw InvalidMap::because(\sprintf('The deepest zoom of a tile source is between 0 and %d, not %d.', self::MAX_ZOOM, $maxZoom));
        }

        $source = new self($template, $attribution, $maxZoom, $allowLocalhost);
        $source->url(0, 0, 0);

        return $source;
    }

    /**
     * The address of one tile.
     *
     * @throws InvalidMap when the tile is outside the source's zooms or its grid, or the template no address
     */
    public function url(int $zoom, int $x, int $y): Url
    {
        if (!$this->has($zoom, $x, $y)) {
            throw InvalidMap::because(\sprintf('There is no tile %d/%d/%d at this source.', $zoom, $x, $y));
        }

        try {
            return Url::fromString(str_replace(self::PLACEHOLDERS, [(string) $zoom, (string) $x, (string) $y], $this->template), $this->allowLocalhost);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidMap::because(\sprintf('The tile template "%s" is no address: %s', $this->template, $invalidUrl->getMessage()));
        }
    }

    /**
     * Whether the tile is in the grid: at zoom z, x and y go from 0 to 2^z - 1.
     */
    public function has(int $zoom, int $x, int $y): bool
    {
        if ($zoom < 0 || $zoom > $this->maxZoom) {
            return false;
        }

        $side = 1 << $zoom;

        return $x >= 0 && $y >= 0 && $x < $side && $y < $side;
    }
}
