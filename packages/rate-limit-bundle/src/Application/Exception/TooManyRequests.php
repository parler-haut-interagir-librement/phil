<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Exception;

use Phil\RateLimitBundle\Domain\Exception\RateLimitException;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * A request a limiter refused: `429 Too Many Requests`, with `Retry-After` and the rate-limit headers of the limiter
 * that refused.
 */
final class TooManyRequests extends TooManyRequestsHttpException implements RateLimitException
{
    /**
     * @param array<string, string> $headers the rate-limit headers, `Retry-After` aside
     */
    public function __construct(
        public readonly Decision $decision,
        array $headers = [],
    ) {
        parent::__construct($decision->retryAfter, 'Too many requests.', null, 0, $headers);
    }
}
