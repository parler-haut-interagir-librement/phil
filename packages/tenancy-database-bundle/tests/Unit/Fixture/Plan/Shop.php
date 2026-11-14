<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Plan;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'shop')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['pop_up' => PopUp::class])]
#[TenantScoped(field: 'tenantRef')]
abstract class Shop
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';
}
