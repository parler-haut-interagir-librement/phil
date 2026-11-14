<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Siblings;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * Two subclasses of a joined hierarchy, declared in the opposite order of their tables' names.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['truck' => Truck::class, 'bike' => Bike::class])]
#[TenantScoped(field: 'tenantRef')]
abstract class Vehicle
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';
}
