<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\Attribute;

use Attribute;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;

/**
 * A controller, or an action, held to a named limiter: each request consumes `$amount` for the key of the request
 * before the controller runs, and is answered 429 Too Many Requests when the limiter refuses it.
 *
 * ```php
 * #[RateLimit('api')]
 * #[RateLimit('exports', amount: 5, key: 'user')]
 * public function export(): Response
 * ```
 *
 * Repeated, every limiter consumes, those of the class before those of the action, in the order written; the first
 * that refuses stops the request, and the ones before it keep what they consumed. The same rule is written as the route
 * default `_rate_limit`, and as the `rate_limit` option of a form.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RateLimit
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
