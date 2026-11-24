<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Purger;

use Override;
use Phil\HttpCacheTagsBundle\Application\CacheTagPurger;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purges Varnish with its xkey module: a `PURGE` request to each Varnish, the tags in one header, separated by spaces.
 * Every Varnish is purged even when one fails; then one `PurgeFailed` names those that refused or could not be
 * reached.
 */
final readonly class VarnishPurger implements CacheTagPurger
{
    /** The longest wait for a byte of an answer, in seconds, by default. */
    public const float TIMEOUT = 5.0;

    /** The longest whole call, in seconds, by default. */
    public const float MAX_DURATION = 10.0;

    /**
     * @param list<string> $urls each Varnish in front of the application
     */
    public function __construct(
        private HttpClientInterface $client,
        private array $urls,
        private string $header = 'xkey-purge',
        private float $timeout = self::TIMEOUT,
        private float $maxDuration = self::MAX_DURATION,
    ) {
    }

    #[Override]
    public function purge(array $tags): void
    {
        $value = implode(' ', array_map(static fn (CacheTag $tag): string => $tag->value, $tags));
        $failures = [];
        $error = null;

        foreach ($this->urls as $url) {
            try {
                $status = $this->client->request('PURGE', $url, ['headers' => [$this->header => $value], 'timeout' => $this->timeout, 'max_duration' => $this->maxDuration])->getStatusCode();
            } catch (TransportExceptionInterface $transportException) {
                $failures[] = PurgeFailed::unreachable('Varnish at '.$url, $transportException);
                $error ??= $transportException;

                continue;
            }

            if ($status >= 400) {
                $failures[] = PurgeFailed::answer('Varnish at '.$url, $status);
            }
        }

        if ([] !== $failures) {
            throw PurgeFailed::of($failures, $error);
        }
    }
}
