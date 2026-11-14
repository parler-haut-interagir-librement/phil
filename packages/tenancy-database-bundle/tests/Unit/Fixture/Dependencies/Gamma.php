<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Dependencies;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'gamma')]
#[TenantScoped(field: 'tenantRef')]
final class Gamma
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';
}
