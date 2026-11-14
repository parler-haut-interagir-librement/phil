<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A label of one tenant, given to invoices through a join table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'label')]
#[TenantScoped(field: 'tenantRef')]
class Label
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'tenant_ref', length: 255)]
        public string $tenantRef,
        #[ORM\Column(length: 255)]
        public string $name,
    ) {
    }
}
