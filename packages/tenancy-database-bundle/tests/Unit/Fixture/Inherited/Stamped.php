<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Inherited;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A mapped superclass that carries the scope itself: it has no table, the tables are its entities'.
 */
#[ORM\MappedSuperclass]
#[TenantScoped(field: 'tenantRef')]
abstract class Stamped
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';
}
