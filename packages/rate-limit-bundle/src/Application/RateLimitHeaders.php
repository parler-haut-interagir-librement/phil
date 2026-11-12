<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;

/**
 * The rate-limit headers of a decision, in the configured style.
 *
 * The draft style writes the two structured fields of the IETF HTTPAPI draft, the policy named after the limiter:
 *
 * ```http
 * RateLimit-Policy: "api";q=100;w=60
 * RateLimit: "api";r=42;t=17
 * ```
 *
 * The legacy style writes `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`. A decision without a limit
 * writes none.
 */
final readonly class RateLimitHeaders
{
    public function __construct(
        private HeaderStyle $style = HeaderStyle::Draft,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function of(Decision $decision): array
    {
        if (null === $decision->limit) {
            return [];
        }

        $headers = [];

        if ($this->style->draft()) {
            $name = '"'.$decision->limiter.'"';
            $headers['RateLimit-Policy'] = $name.';q='.$decision->limit.(null === $decision->window ? '' : ';w='.$decision->window);
            $headers['RateLimit'] = $name.';r='.$decision->remaining.';t='.$decision->resetAfter;
        }

        if ($this->style->legacy()) {
            $headers['RateLimit-Limit'] = (string) $decision->limit;
            $headers['RateLimit-Remaining'] = (string) $decision->remaining;
            $headers['RateLimit-Reset'] = (string) $decision->resetAfter;
        }

        return $headers;
    }
}
