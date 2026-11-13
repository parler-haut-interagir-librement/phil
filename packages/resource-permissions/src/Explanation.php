<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

/**
 * Why a subject holds a relation: the tuples followed from the object to the subject, the first one on the object.
 * An intersection gives the tuples of each of its operands, one after the other.
 */
final readonly class Explanation
{
    /**
     * @param list<RelationTuple> $tuples
     */
    public function __construct(
        public array $tuples,
    ) {
    }

    /**
     * The tuples as lines of text, `object#relation@subject`.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (RelationTuple $tuple): string => $tuple->key(), $this->tuples);
    }
}
