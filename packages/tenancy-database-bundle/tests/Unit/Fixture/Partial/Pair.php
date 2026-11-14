<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Partial;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A tenant-scoped entity with a key of two columns, whose many-to-many association has a join column of one: the join
 * table cannot be reached through the key.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pair')]
#[TenantScoped(field: 'tenantRef')]
final class Pair
{
    #[ORM\Id]
    #[ORM\Column(name: 'a')]
    public int $first = 0;

    #[ORM\Id]
    #[ORM\Column(name: 'b')]
    public int $second = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    /** @var Collection<int, Mark> */
    #[ORM\ManyToMany(targetEntity: Mark::class)]
    #[ORM\JoinTable(name: 'pair_mark')]
    #[ORM\JoinColumn(name: 'pair_a', referencedColumnName: 'a')]
    public Collection $marks;

    public function __construct()
    {
        $this->marks = new ArrayCollection();
    }
}
