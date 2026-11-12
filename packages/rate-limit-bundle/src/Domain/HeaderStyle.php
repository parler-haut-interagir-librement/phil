<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain;

/**
 * The rate-limit headers a response carries.
 */
enum HeaderStyle: string
{
    /** `RateLimit-Policy` and `RateLimit`, the structured fields of the IETF HTTPAPI draft. */
    case Draft = 'draft';

    /** `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`, the three fields of the earlier drafts. */
    case Legacy = 'legacy';

    /** Both sets. */
    case Both = 'both';

    /** None: a refusal keeps `Retry-After` alone. */
    case None = 'none';

    public function draft(): bool
    {
        return self::Draft === $this || self::Both === $this;
    }

    public function legacy(): bool
    {
        return self::Legacy === $this || self::Both === $this;
    }
}
