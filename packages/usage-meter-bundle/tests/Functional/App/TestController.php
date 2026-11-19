<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Functional\App;

use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two actions behind limiters that count on meters: one by IP address, one by the account a header names.
 */
final class TestController
{
    #[RateLimit('api')]
    public function search(): Response
    {
        return new Response('found');
    }

    #[RateLimit('accounts', amount: 2)]
    public function export(): Response
    {
        return new Response('exported');
    }
}
