<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Support;

use Phil\RelatedContent\Contract\TypedRelations;
use Phil\RelatedContent\Relation\TypedRelation;

/**
 * Typed relations in memory; it answers `from()` and `to()` with every relation it holds, so that the engine is seen
 * reading only those that start, or end, at the item.
 */
final readonly class MemoryRelations implements TypedRelations
{
    /**
     * @var list<TypedRelation>
     */
    private array $relations;

    public function __construct(TypedRelation ...$relations)
    {
        $this->relations = array_values($relations);
    }

    public function from(string $id): array
    {
        return $this->relations;
    }

    public function to(string $id): array
    {
        return $this->relations;
    }
}
