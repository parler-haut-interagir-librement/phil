<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub;

use JsonException;
use OpenSSLAsymmetricKey;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The public keys GitHub signs its secret scanning alerts with, read from the address it publishes them at and kept in
 * a cache pool, shared by every worker: nothing is kept in the object between requests.
 *
 * A key the pool does not hold is looked for again at GitHub, which rotates its keys, but at most once per refresh
 * interval, so that requests naming unknown keys cannot make the application call GitHub on each of them. A failed
 * fetch is kept for one refresh interval, not for the whole lifetime.
 */
final readonly class GitHubPublicKeys
{
    public const string DEFAULT_URL = 'https://api.github.com/meta/public_keys/secret_scanning';

    /** The longest answer read. */
    public const int MAX_BYTES = 65_536;

    /** The most keys kept. */
    public const int MAX_KEYS = 32;

    /** The longest key identifier, and the longest key in PEM. */
    public const int MAX_IDENTIFIER_LENGTH = 256;

    public const int MAX_KEY_LENGTH = 4_096;

    public const string CACHE_KEY = 'phil_api_keys.github_secret_scanning_keys';

    /**
     * @param positive-int $lifetime        the seconds the keys are kept
     * @param positive-int $refreshInterval the fewest seconds between two fetches, and the seconds a failed fetch is kept
     * @param positive-int $timeout         the seconds a fetch may take
     */
    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private ClockInterface $clock,
        private string $url = self::DEFAULT_URL,
        private int $lifetime = 86_400,
        private int $refreshInterval = 60,
        private int $timeout = 5,
    ) {
    }

    /**
     * The key in PEM under that identifier, or null when GitHub publishes none.
     */
    public function key(string $identifier): ?string
    {
        $keys = $this->cache->get(self::CACHE_KEY, $this->fetch(...));

        if (isset($keys['keys'][$identifier])) {
            return $keys['keys'][$identifier];
        }

        if ($this->clock->now()->getTimestamp() - $keys['fetched_at'] < $this->refreshInterval) {
            return null;
        }

        $this->cache->delete(self::CACHE_KEY);

        return $this->cache->get(self::CACHE_KEY, $this->fetch(...))['keys'][$identifier] ?? null;
    }

    /**
     * The keys of a document GitHub publishes, by identifier: those whose PEM does not read as a public key are left
     * out.
     *
     * @return array<string, string>
     *
     * @throws UnreadableReport when the document is not the shape expected, or passes a bound
     */
    public static function parse(string $json): array
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw UnreadableReport::because(\sprintf('The public keys of GitHub are %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $document = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw UnreadableReport::because('The public keys of GitHub are JSON.', $jsonException);
        }

        $entries = \is_array($document) ? $document['public_keys'] ?? null : null;

        if (!\is_array($entries) || !array_is_list($entries) || \count($entries) > self::MAX_KEYS) {
            throw UnreadableReport::because(\sprintf('The public keys of GitHub are a list of %d keys at most.', self::MAX_KEYS));
        }

        $keys = [];

        foreach ($entries as $entry) {
            $identifier = \is_array($entry) ? $entry['key_identifier'] ?? null : null;
            $pem = \is_array($entry) ? $entry['key'] ?? null : null;

            if (!\is_string($identifier) || '' === $identifier || \strlen($identifier) > self::MAX_IDENTIFIER_LENGTH || !\is_string($pem) || \strlen($pem) > self::MAX_KEY_LENGTH) {
                throw UnreadableReport::because('Each public key of GitHub has an identifier and a key, within bounds.');
            }

            if (openssl_pkey_get_public($pem) instanceof OpenSSLAsymmetricKey) {
                $keys[$identifier] = $pem;
            }
        }

        self::clearOpenSslErrors();

        return $keys;
    }

    /**
     * Empties OpenSSL's queue of errors, which a PEM that does not read fills and which would otherwise outlive the
     * request in a worker: each call takes one message off the queue.
     */
    private static function clearOpenSslErrors(): void
    {
        while (false !== openssl_error_string()) {
        }
    }

    /**
     * @return array{fetched_at: int, keys: array<string, string>}
     */
    private function fetch(ItemInterface $item): array
    {
        $fetchedAt = $this->clock->now()->getTimestamp();

        try {
            $response = $this->http->request('GET', $this->url, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
                'max_redirects' => 0,
            ]);

            if (200 !== $response->getStatusCode()) {
                $response->cancel();

                throw UnreadableReport::because('GitHub did not give its public keys.');
            }

            $body = '';

            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > self::MAX_BYTES) {
                    $response->cancel();

                    throw UnreadableReport::because('The answer of GitHub is too long.');
                }
            }

            $keys = self::parse($body);
        } catch (ExceptionInterface|UnreadableReport) {
            $item->expiresAfter($this->refreshInterval);

            return ['fetched_at' => $fetchedAt, 'keys' => []];
        }

        $item->expiresAfter($this->lifetime);

        return ['fetched_at' => $fetchedAt, 'keys' => $keys];
    }
}
