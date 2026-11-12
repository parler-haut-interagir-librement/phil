<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

/**
 * A controller limited on its class and on one action, with an action naming a limiter nothing declares and one whose
 * attribute is written wrong.
 */
#[RateLimit('known')]
final class LimitedController
{
    public function __invoke(): void
    {
    }

    #[RateLimit('other', key: 'ip')]
    public function listed(): string
    {
        return 'listed';
    }

    #[RateLimit('nothing')]
    public function unknown(): string
    {
        return 'unknown';
    }

    #[RateLimit('nothing', key: 'ip')]
    public function unknownLimiter(): string
    {
        return 'unknownLimiter';
    }

    #[RateLimit('known', key: 'nothing')]
    public function unknownKey(): string
    {
        return 'unknownKey';
    }

    #[RateLimit('')]
    public function invalid(): string
    {
        return 'invalid';
    }
}
