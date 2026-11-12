<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;

/**
 * The rules a route default `_rate_limit` or a form's `rate_limit` option holds, read as `RateLimit` attributes.
 *
 * One rule is a limiter's name, a `RateLimit`, or a map with `limiter` and, optionally, `amount` and `key`; the value
 * is one rule or a list of up to sixteen. Null is no rule.
 *
 * ```yaml
 * defaults:
 *     _rate_limit: [api, { limiter: search, key: ip_route }]
 * ```
 */
final class RateLimitRules
{
    /** The most rules one value holds. */
    public const int MAX_RULES = 16;

    /**
     * @return list<RateLimit>
     *
     * @throws InvalidRateLimit when the value is of another shape
     */
    public static function from(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!\is_array($value) || \array_key_exists('limiter', $value)) {
            return [self::one($value)];
        }

        if (!array_is_list($value) || \count($value) > self::MAX_RULES) {
            throw InvalidRateLimit::because(\sprintf('A list of rate limits holds at most %d rules, one after the other.', self::MAX_RULES));
        }

        return array_map(self::one(...), $value);
    }

    private static function one(mixed $rule): RateLimit
    {
        if ($rule instanceof RateLimit) {
            return $rule;
        }

        if (\is_string($rule)) {
            return new RateLimit($rule);
        }

        if (\is_array($rule) && \is_string($rule['limiter'] ?? null) && [] === array_diff(array_keys($rule), ['limiter', 'amount', 'key'])) {
            $amount = $rule['amount'] ?? null;
            $key = $rule['key'] ?? null;

            if ((null === $amount || \is_int($amount)) && (null === $key || \is_string($key))) {
                return new RateLimit($rule['limiter'], $amount, $key);
            }
        }

        throw InvalidRateLimit::because('A rate limit is a limiter\'s name, or a map of "limiter", an integer "amount" and a string "key".');
    }
}
