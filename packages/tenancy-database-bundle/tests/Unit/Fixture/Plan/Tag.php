<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Plan;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'tag')]
#[TenantScoped(field: 'tenantRef')]
final class Tag
{
    #[ORM\Id]
    #[ORM\Column]
    public string $code = '';

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    #[ORM\ManyToOne(targetEntity: self::class)]
    public ?self $parent = null;
}
