<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The choice the account made, carried by the request in `X-Active-Tenant` — where an application would read its
 * session. What the application asks of the store is recorded, to be read by the tests.
 */
final class HeaderActiveTenantStore implements ActiveTenantStore
{
    public ?TenantId $remembered = null;

    public int $forgotten = 0;

    public function __construct(
        private readonly RequestStack $requests,
    ) {
    }

    public function remembered(): ?TenantId
    {
        $header = $this->requests->getMainRequest()?->headers->get('X-Active-Tenant');

        return null === $header ? $this->remembered : TenantId::fromString($header);
    }

    public function remember(TenantId $tenant): void
    {
        $this->remembered = $tenant;
    }

    public function forget(): void
    {
        $this->remembered = null;
        ++$this->forgotten;
    }
}
