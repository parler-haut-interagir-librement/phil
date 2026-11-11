<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps what a geocoder answered for a while, so the same address does not cost a request — and a quota — twice. A
 * failure is not kept.
 */
final readonly class CachedGeocoder implements Geocoder
{
    public function __construct(
        private Geocoder $geocoder,
        private CacheInterface $cache,
        private int $lifetime = 604800,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->kept('geocode', $address, $locale, fn (): array => $this->geocoder->geocode($address, $locale));
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->kept('reverse', $at->toString(), $locale, fn (): array => $this->geocoder->reverse($at, $locale));
    }

    /**
     * @param callable():list<Place> $ask
     *
     * @return list<Place>
     */
    private function kept(string $operation, string $query, ?string $locale, callable $ask): array
    {
        return $this->cache->get('phil_geo.'.hash('xxh128', implode("\0", [$operation, $query, $locale])), function (ItemInterface $item) use ($ask): array {
            $item->expiresAfter($this->lifetime);

            return $ask();
        });
    }
}
