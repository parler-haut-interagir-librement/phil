<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Plan;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[ORM\Entity]
#[ORM\Table(name: 'b_order')]
#[TenantScoped(field: 'tenantRef')]
final class Order
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(name: 'tenant_ref')]
    public string $tenantRef = '';

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    public ?Shop $shop = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    public ?Country $country = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'b_order_tag')]
    public Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}
