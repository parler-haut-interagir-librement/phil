<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture;

use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;
use Symfony\Component\HttpFoundation\Response;

/**
 * A controller whose deprecation date cannot be read.
 */
final class MisdatedController
{
    #[DeprecatedEndpoint(deprecatedAt: '2026-11-01')]
    public function fine(): Response
    {
        return new Response('fine');
    }

    #[DeprecatedEndpoint(sunsetAt: '2027-13-01')]
    public function old(): Response
    {
        return new Response('old');
    }
}
