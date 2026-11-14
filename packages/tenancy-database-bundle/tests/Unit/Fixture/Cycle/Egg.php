<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Cycle;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'egg')]
#[TenantScoped(field: 'tenantRef')]
final class Egg
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    #[ORM\ManyToOne(targetEntity: Hen::class)]
    public ?Hen $hen = null;
}
