<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A customer of one tenant. Its table sorts after the invoices' that reference it: a move must order the tables by
 * their references, not by their names.
 */
#[ORM\Entity]
#[ORM\Table(name: 'party')]
#[TenantScoped(field: 'tenantRef')]
class Party
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
