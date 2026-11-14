<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * An invoice of one tenant, for one of its customers, with labels, and a binary scan.
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

    /** @var Collection<int, Label> */
    #[ORM\ManyToMany(targetEntity: Label::class)]
    #[ORM\JoinTable(name: 'invoice_label')]
    public Collection $labels;

    /**
     * @param resource|string|null $scan binary content
     */
    public function __construct(
        #[ORM\Column(name: 'tenant_ref', length: 255)]
        public string $tenantRef,
        #[ORM\Column(length: 255)]
        public string $label,
        #[ORM\ManyToOne(targetEntity: Party::class)]
        #[ORM\JoinColumn(nullable: false)]
        public Party $party,
        #[ORM\Column(type: Types::BLOB, nullable: true)]
        public mixed $scan = null,
        #[ORM\Column]
        public bool $paid = false,
    ) {
        $this->labels = new ArrayCollection();
    }
}
