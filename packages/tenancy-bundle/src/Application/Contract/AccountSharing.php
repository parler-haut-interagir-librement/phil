<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Whether an account signed in on one tenant — or on no tenant, on a central host — is signed in on another. An
 * account is signed in where it signed in and nowhere else, unless the application declares it shared.
 */
interface AccountSharing
{
    /**
     * Asked only when the two tenants differ: never with two `null`.
     *
     * @param string    $account    the identifier of the authenticated account, as the user provider gives it
     * @param ?TenantId $signedInOn the tenant the account signed in on, or `null` for a session opened in no tenant
     * @param ?TenantId $visited    the tenant of the current request, or `null` for a request in no tenant
     */
    public function isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited): bool;
}
