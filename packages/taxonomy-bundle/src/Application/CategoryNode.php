<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Application;

use Phil\TaxonomyBundle\Domain\Model\Category;

/**
 * A category in a tree, with its name in a language and its children.
 */
final readonly class CategoryNode
{
    /**
     * @param list<CategoryNode> $children
     */
    public function __construct(
        public Category $category,
        public string $name,
        public array $children,
    ) {
    }
}
