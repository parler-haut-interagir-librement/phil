<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver;
use Phil\Idempotency\IdempotencyScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * One scope for every request, so that the tests of the listener read their records by a name.
 */
final readonly class FixedScopeResolver implements IdempotencyScopeResolver
{
    public function __construct(
        private string $scope = 'ip:10.0.0.1',
    ) {
    }

    #[Override]
    public function scope(Request $request): IdempotencyScope
    {
        return IdempotencyScope::of($this->scope);
    }
}
