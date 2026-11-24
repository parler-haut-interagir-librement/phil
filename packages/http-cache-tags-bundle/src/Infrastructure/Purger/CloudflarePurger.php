<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Purger;

use Override;
use Phil\HttpCacheTagsBundle\Application\CacheTagPurger;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purges Cloudflare by tag, through its API: a hundred tags per call at most. Every call is made even when one fails;
 * then one `PurgeFailed` says which failed.
 */
final readonly class CloudflarePurger implements CacheTagPurger
{
    public const int TAGS_PER_CALL = 100;

    /** The longest wait for a byte of an answer, in seconds, by default. */
    public const float TIMEOUT = 5.0;

    /** The longest whole call, in seconds, by default. */
    public const float MAX_DURATION = 10.0;

    public function __construct(
        private HttpClientInterface $client,
        private string $zone,
        #[SensitiveParameter]
        private string $token,
        private float $timeout = self::TIMEOUT,
        private float $maxDuration = self::MAX_DURATION,
    ) {
    }

    #[Override]
    public function purge(array $tags): void
    {
        $failures = [];
        $error = null;

        foreach (array_chunk(array_map(static fn (CacheTag $tag): string => $tag->value, $tags), self::TAGS_PER_CALL) as $call => $chunk) {
            $what = 0 === $call ? 'Cloudflare' : \sprintf('Cloudflare, for tags %d to %d,', $call * self::TAGS_PER_CALL + 1, $call * self::TAGS_PER_CALL + \count($chunk));

            try {
                $status = $this->client->request('POST', \sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', rawurlencode($this->zone)), [
                    'auth_bearer' => $this->token,
                    'json' => ['tags' => $chunk],
                    'timeout' => $this->timeout,
                    'max_duration' => $this->maxDuration,
                ])->getStatusCode();
            } catch (TransportExceptionInterface $transportException) {
                $failures[] = PurgeFailed::unreachable($what, $transportException);
                $error ??= $transportException;

                continue;
            }

            if ($status >= 400) {
                $failures[] = PurgeFailed::answer($what, $status);
            }
        }

        if ([] !== $failures) {
            throw PurgeFailed::of($failures, $error);
        }
    }
}
