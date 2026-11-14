<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Inherited;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * An embeddable that carries the scope itself: it has no table either.
 */
#[ORM\Embeddable]
#[TenantScoped(field: 'tenantRef')]
final class Postcode
{
    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';
}
