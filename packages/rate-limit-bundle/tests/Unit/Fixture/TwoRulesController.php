<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Symfony\Component\HttpKernel\Attribute\RateLimit;

/**
 * A controller limited twice on its class by Symfony's attribute, the second naming a limiter nothing declares.
 */
#[RateLimit('known')]
#[RateLimit('nothing')]
final class TwoRulesController
{
    public function __invoke(): void
    {
    }
}
