<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding;

use Phil\Geo\Exception\InvalidPlace;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * How long a call to a provider may take: `timeout`, the longest wait for a byte of the answer, and `maxDuration`,
 * the longest whole call, both in seconds. Every HTTP adapter sets them on its client, so that a slow provider holds
 * a worker for seconds rather than for `default_socket_timeout`.
 */
final readonly class Timeouts
{
    public const float TIMEOUT = 3.0;

    public const float MAX_DURATION = 5.0;

    /** The longest either may be, in seconds. */
    public const float MAX = 120.0;

    /**
     * @throws InvalidPlace when either is not above zero and at most `MAX`, or the whole call is shorter than the wait
     */
    public function __construct(
        public float $timeout = self::TIMEOUT,
        public float $maxDuration = self::MAX_DURATION,
    ) {
        if ($timeout <= 0.0 || $timeout > self::MAX || $maxDuration > self::MAX) {
            throw InvalidPlace::timeouts($timeout, $maxDuration);
        }

        // The whole call is never shorter than the wait, which is above zero: a duration of zero or less is refused here.
        if ($maxDuration < $timeout) {
            throw InvalidPlace::timeouts($timeout, $maxDuration);
        }
    }

    /**
     * The client with these limits on every request it sends.
     */
    public function apply(HttpClientInterface $client): HttpClientInterface
    {
        return $client->withOptions(['timeout' => $this->timeout, 'max_duration' => $this->maxDuration]);
    }
}
