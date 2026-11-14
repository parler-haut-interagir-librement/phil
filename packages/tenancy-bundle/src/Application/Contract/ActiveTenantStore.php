<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Where the application remembers the tenant the account chose.
 *
 * **The semantics belong to the application**: sticky per browser tab, per session, carried in the URL. None of
 * them can be decided honestly for every application, and none of them may live in the identity provider's
 * session or in the ID token, which span every application while an active tenant belongs to one.
 */
interface ActiveTenantStore
{
    public function remembered(): ?TenantId;

    public function remember(TenantId $tenant): void;

    public function forget(): void;
}
