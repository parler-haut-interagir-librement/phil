<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture;

use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;

/**
 * A controller, deprecated as a whole, that goes away before it is deprecated.
 */
#[DeprecatedEndpoint(deprecatedAt: '2027-05-01', sunsetAt: '2026-11-01')]
final class BackwardsController
{
}
