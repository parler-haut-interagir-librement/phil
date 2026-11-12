<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

/**
 * A controller limited twice on its class, the second rule naming a limiter nothing declares.
 */
#[RateLimit('known')]
#[RateLimit('nothing')]
final class TwoRulesController
{
    public function __invoke(): void
    {
    }
}
