<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional\App;

use Override;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * The tenant of the test application, one per host it serves: `acme.test` and `globex.test`. An application reads the
 * tenant it verified; the test only needs two tenants on one store.
 */
final readonly class HostTenant implements IdempotencyTenantResolver
{
    private const array TENANTS = ['acme.test' => 'acme', 'globex.test' => 'globex'];

    #[Override]
    public function tenant(Request $request): ?string
    {
        return self::TENANTS[$request->getHost()] ?? null;
    }
}
