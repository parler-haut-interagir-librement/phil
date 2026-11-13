<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Model;

use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnknownRelation;
use Phil\ResourcePermissions\RelationName;

/**
 * A kind of object and its relations: `folder` with `parent`, `owner` and `viewer`. A kind that is only ever a
 * subject — `member` — is declared without relations.
 */
final readonly class TypeDefinition
{
    /**
     * @param array<string, Rewrite> $relations
     */
    private function __construct(
        public string $kind,
        public array $relations,
    ) {
    }

    /**
     * @param array<string, Rewrite> $relations each relation's name and how it is computed
     */
    public static function of(string $kind, array $relations = []): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $kind)) {
            throw InvalidRelation::because(\sprintf('A kind is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $kind));
        }

        foreach (array_keys($relations) as $relation) {
            RelationName::assert($relation);
        }

        return new self($kind, $relations);
    }

    public function has(string $relation): bool
    {
        return isset($this->relations[$relation]);
    }

    public function rewrite(string $relation): Rewrite
    {
        return $this->relations[$relation] ?? throw UnknownRelation::relation($this->kind, $relation);
    }

    /**
     * The same kind with more relations; a relation already declared is refused.
     *
     * @param array<string, Rewrite> $relations
     */
    public function with(array $relations): self
    {
        foreach (array_keys($relations) as $relation) {
            if (isset($this->relations[$relation])) {
                throw InvalidRelation::because(\sprintf('The kind "%s" already declares the relation "%s".', $this->kind, $relation));
            }
        }

        return self::of($this->kind, [...$this->relations, ...$relations]);
    }
}
