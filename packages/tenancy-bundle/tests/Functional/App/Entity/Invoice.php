<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * An entity owned by one tenant, its reference held in a mapped field.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice')]
#[TenantScoped(field: 'tenantRef')]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'tenant_ref', length: 255)]
        public string $tenantRef,
        #[ORM\Column(length: 255)]
        public string $label,
    ) {
    }
}
