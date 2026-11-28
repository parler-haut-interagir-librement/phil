<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\ValueObject;

use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;

/**
 * One rule of the route default `_rate_limit` or of the `rate_limit` option of a form: a named limiter, what one
 * request consumes on it, and who the request counts for.
 *
 * ```yaml
 * defaults:
 *     _rate_limit: [api, { limiter: exports, amount: 5, key: user }]
 * ```
 *
 * Controllers carry Symfony's own `#[RateLimit]` attribute instead, which names the same limiters.
 */
final readonly class Rule
{
    /**
     * @throws InvalidRateLimit when the name or the key is empty, or the amount below one
     */
    public function __construct(
        /** The name of a declared limiter. */
        public string $limiter,
        /** What one request consumes, one or more; null consumes the limiter's own amount. */
        public ?int $amount = null,
        /** Who a request counts for: "ip", "user", "user_or_ip", "ip_route", or a key resolver's service id; null takes the limiter's own. */
        public ?string $key = null,
    ) {
        if ('' === $limiter) {
            throw InvalidRateLimit::because('A rate limit names its limiter.');
        }

        if (null !== $amount && $amount < 1) {
            throw InvalidRateLimit::because(\sprintf('A rate limit consumes one or more; %d is not.', $amount));
        }

        if ('' === $key) {
            throw InvalidRateLimit::because("A rate limit names its key strategy, or leaves it null for the limiter's own.");
        }
    }
}
