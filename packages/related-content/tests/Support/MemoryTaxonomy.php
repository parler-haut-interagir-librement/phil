<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Support;

use Phil\RelatedContent\Contract\Taxonomy;

final class MemoryTaxonomy implements Taxonomy
{
    /**
     * @var list<string>
     */
    public array $asked = [];

    /**
     * @param array<string, list<string>> $parents by term
     */
    public function __construct(
        private readonly array $parents,
    ) {
    }

    public function broader(string $index, array $terms): array
    {
        $this->asked[] = $index;
        $broader = [];

        foreach ($terms as $term) {
            foreach ($this->parents[$term] ?? [] as $parent) {
                $broader[] = $parent;
            }
        }

        return $broader;
    }
}
