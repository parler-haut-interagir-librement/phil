<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Mailer\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Mailer\TenantMail;

/**
 * How each tenant's mail leaves.
 */
interface TenantMailSettings
{
    /**
     * @return ?TenantMail the tenant's settings, or `null` for a tenant whose mail leaves as the application's
     */
    public function for(TenantId $tenant): ?TenantMail;
}
