<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Composite;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A tenant-scoped entity with a key of two columns, and a many-to-many association: its join table is reached
 * through both.
 */
#[ORM\Entity]
#[ORM\Table(name: 'line')]
#[TenantScoped(field: 'tenantRef')]
final class Line
{
    #[ORM\Id]
    #[ORM\Column]
    public int $document = 0;

    #[ORM\Id]
    #[ORM\Column]
    public int $position = 0;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    /** @var Collection<int, Line> */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'line_link')]
    #[ORM\JoinColumn(name: 'from_document', referencedColumnName: 'document')]
    #[ORM\JoinColumn(name: 'from_position', referencedColumnName: 'position')]
    #[ORM\InverseJoinColumn(name: 'to_document', referencedColumnName: 'document')]
    #[ORM\InverseJoinColumn(name: 'to_position', referencedColumnName: 'position')]
    public Collection $links;

    public function __construct()
    {
        $this->links = new ArrayCollection();
    }
}
