<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use Override;
use Phil\Lti\Contract\KeySetSource;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps the key sets another source reads in a cache, for an hour by default. A refresh reads a set again, at most
 * once a minute per address by default, so that tokens naming unknown keys cannot make the side read the set at every
 * request.
 */
final readonly class CachedKeySetSource implements KeySetSource
{
    public const int DEFAULT_LIFETIME = 3600;

    public const int DEFAULT_REFRESH_INTERVAL = 60;

    public function __construct(
        private KeySetSource $source,
        private CacheInterface $cache,
        private ClockInterface $clock,
        private int $lifetime = self::DEFAULT_LIFETIME,
        private int $refreshInterval = self::DEFAULT_REFRESH_INTERVAL,
    ) {
    }

    #[Override]
    public function keySet(Url $url, bool $refresh = false): KeySet
    {
        $key = 'phil_lti.jwks.'.hash('xxh128', $url->toString());

        if ($refresh) {
            /** @var array{at: int, json: string} $held */
            $held = $this->cache->get($key, fn (ItemInterface $item): array => $this->read($url, $item));

            if ($this->clock->now()->getTimestamp() - $held['at'] < $this->refreshInterval) {
                return KeySet::fromJson($held['json']);
            }

            $this->cache->delete($key);
        }

        /** @var array{at: int, json: string} $held */
        $held = $this->cache->get($key, fn (ItemInterface $item): array => $this->read($url, $item));

        return KeySet::fromJson($held['json']);
    }

    /**
     * @return array{at: int, json: string}
     */
    private function read(Url $url, ItemInterface $item): array
    {
        $item->expiresAfter($this->lifetime);

        return ['at' => $this->clock->now()->getTimestamp(), 'json' => $this->source->keySet($url, true)->toJson()];
    }
}
