<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Dependencies;

use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'a_line')]
#[TenantScoped(field: 'tenantRef')]
final class ALine
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    /** The first reference is not the last to be waited for: the one to Beta, which itself waits for Gamma. */
    #[ORM\ManyToOne(targetEntity: Zeta::class)]
    public ?Zeta $zeta = null;

    #[ORM\ManyToOne(targetEntity: Beta::class)]
    public ?Beta $beta = null;
}
