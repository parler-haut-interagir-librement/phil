<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Symfony\Component\HttpKernel\Attribute\RateLimit;

/**
 * A controller limited by Symfony's attribute on its class and on its actions: a limiter of the bundle, one of the
 * framework's configuration, and one nothing declares.
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

    #[RateLimit('framework_limit')]
    public function framework(): string
    {
        return 'framework';
    }

    #[RateLimit('nothing')]
    public function unknown(): string
    {
        return 'unknown';
    }
}
