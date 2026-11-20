<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * The products of one design made on one template, shown as one page with a selector per axis: each product stays a
 * product of the catalogue, with its own SKU, and is a member of the group.
 *
 * A group is withdrawn rather than deleted, with its members and their products, so that the orders that name them
 * keep their meaning.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_product_group')]
#[ORM\UniqueConstraint(columns: ['design', 'template'])]
final class ProductGroup
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 65)]
        public readonly string $id,
        #[ORM\Column(name: 'design', length: 32)]
        public readonly string $design,
        #[ORM\Column(name: 'template', length: 32)]
        public readonly string $template,
    ) {
    }

    public static function of(string $design, string $template): self
    {
        return new self(self::idOf($design, $template), $design, $template);
    }

    /**
     * The identifier of the group of a design on a template: `<design>:<template>`, unambiguous since neither code
     * holds a colon.
     */
    public static function idOf(string $design, string $template): string
    {
        return $design.':'.$template;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }
}
