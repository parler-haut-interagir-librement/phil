<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;

final class InMemoryDesigns implements ProductDesignRepository
{
    /** @var array<string, ProductDesign> */
    private array $designs = [];

    public function __construct(ProductDesign ...$designs)
    {
        foreach ($designs as $design) {
            $this->add($design);
        }
    }

    #[Override]
    public function find(string $code): ?ProductDesign
    {
        return $this->designs[$code] ?? null;
    }

    #[Override]
    public function add(ProductDesign $design): void
    {
        $this->designs[$design->code] = $design;
    }
}
