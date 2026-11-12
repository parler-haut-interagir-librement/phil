<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Deprecation;

use DateTimeImmutable;
use Override;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The deprecation signals received, endpoint by endpoint, so that a warning is logged once a day rather than once a
 * call; without a pool, in memory for the process.
 *
 * In a cache pool, each endpoint is an item of its own, and an index item names the endpoints. An endpoint's item is
 * written only when its daily warning is due or its signal changed, never on every call, and two processes recording
 * different endpoints never overwrite each other's item. Each write keeps the item {@see self::LIFETIME} seconds: an
 * endpoint no longer called, or whose API withdrew its signal, is forgotten a week after it was last recorded.
 *
 * The index is rewritten only when an endpoint is missing from it, and checked again at each write of an endpoint's
 * item: a name another process dropped while both added theirs comes back within a day.
 */
final class CacheObservedDeprecations implements ObservedDeprecations
{
    /** The index item: the endpoints recorded. */
    public const string KEY = 'phil_api_contract.observed_deprecations';

    /** How long an endpoint's record is kept after it was written: a day of warning, then a week. */
    public const int LIFETIME = 8 * 86_400;

    private const int DAY = 86_400;

    /** @var array<string, array{endpoint: string, headers: array<string, string>, at: int}> */
    private array $memory = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    #[Override]
    public function record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool
    {
        $entry = $this->entry($endpoint);
        $headers = $signal->toHeaders();
        $fresh = null === $entry || $entry['at'] <= $observedAt->getTimestamp() - self::DAY;

        if ($fresh || $entry['headers'] !== $headers) {
            $this->write(['endpoint' => $endpoint, 'headers' => $headers, 'at' => $fresh ? $observedAt->getTimestamp() : $entry['at']], $observedAt);
        }

        return $fresh;
    }

    #[Override]
    public function all(): array
    {
        $signals = [];

        foreach ($this->endpoints() as $endpoint) {
            $entry = $this->entry($endpoint);

            if (null === $entry) {
                continue;
            }

            $headers = $entry['headers'];
            $signal = DeprecationSignal::fromHeaders($headers[DeprecationSignal::DEPRECATION_HEADER] ?? null, $headers[DeprecationSignal::SUNSET_HEADER] ?? null, isset($headers[DeprecationSignal::LINK_HEADER]) ? [$headers[DeprecationSignal::LINK_HEADER]] : []);

            if ($signal instanceof DeprecationSignal) {
                $signals[$endpoint] = $signal;
            }
        }

        return $signals;
    }

    /**
     * The item key of an endpoint: a hash, since an endpoint holds characters a cache key may not.
     */
    public static function keyOf(string $endpoint): string
    {
        return self::KEY.'.'.hash('xxh128', $endpoint);
    }

    /**
     * @return array{headers: array<string, string>, at: int}|null
     */
    private function entry(string $endpoint): ?array
    {
        if (!$this->cache instanceof CacheItemPoolInterface) {
            return $this->memory[$endpoint] ?? null;
        }

        return $this->entryOf($this->cache->getItem(self::keyOf($endpoint))->get(), $endpoint);
    }

    /**
     * @param array{endpoint: string, headers: array<string, string>, at: int} $entry
     */
    private function write(array $entry, DateTimeImmutable $observedAt): void
    {
        if (!$this->cache instanceof CacheItemPoolInterface) {
            $this->memory[$entry['endpoint']] = $entry;

            return;
        }

        $cache = $this->cache;
        $cache->save($cache->getItem(self::keyOf($entry['endpoint']))->set($entry)->expiresAt($observedAt->modify(\sprintf('+%d seconds', self::LIFETIME))));

        $index = $this->endpoints();

        if (!\in_array($entry['endpoint'], $index, true)) {
            // Names whose record expired are dropped while the index is rewritten anyway.
            $kept = array_filter($index, static fn (string $endpoint): bool => $cache->hasItem(self::keyOf($endpoint)));
            $cache->save($cache->getItem(self::KEY)->set([...$kept, $entry['endpoint']]));
        }
    }

    /**
     * @return list<string>
     */
    private function endpoints(): array
    {
        if (!$this->cache instanceof CacheItemPoolInterface) {
            return array_keys($this->memory);
        }

        $stored = $this->cache->getItem(self::KEY)->get();

        return \is_array($stored) ? array_values(array_filter($stored, \is_string(...))) : [];
    }

    /**
     * An item's value, when it is an entry this class wrote for this endpoint.
     *
     * @return array{headers: array<string, string>, at: int}|null
     */
    private function entryOf(mixed $value, string $endpoint): ?array
    {
        if (!\is_array($value) || ($value['endpoint'] ?? null) !== $endpoint || !\is_int($value['at'] ?? null) || !\is_array($value['headers'] ?? null)) {
            return null;
        }

        $headers = [];

        foreach ($value['headers'] as $name => $line) {
            if (\is_string($name) && \is_string($line)) {
                $headers[$name] = $line;
            }
        }

        return ['headers' => $headers, 'at' => $value['at']];
    }
}
