<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Model;

use Phil\ResourcePermissions\RelationName;

/**
 * How one relation of a kind is computed from the tuples: the tuples written on it, another relation of the same
 * object, a relation of the objects it points at, or a union, an intersection or an exclusion of those.
 *
 * ```php
 * Rewrite::union(
 *     Rewrite::direct('member', 'member:*', 'group#member'),
 *     Rewrite::computed('editor'),
 *     Rewrite::fromRelated('parent', 'viewer'),
 * )
 * ```
 */
final readonly class Rewrite
{
    /**
     * @param list<SubjectType> $types    the subjects a direct relation accepts
     * @param list<self>        $children the operands of a union, an intersection or an exclusion
     */
    private function __construct(
        public RewriteKind $kind,
        public array $types = [],
        public ?string $relation = null,
        public ?string $via = null,
        public array $children = [],
    ) {
    }

    /**
     * The tuples written on the relation, given to subjects of the types named: `member`, `member:*`, `group#member`.
     */
    public static function direct(string $type, string ...$types): self
    {
        return new self(RewriteKind::Direct, array_map(SubjectType::fromString(...), [$type, ...array_values($types)]));
    }

    /**
     * Every subject holding `$relation` on the same object.
     */
    public static function computed(string $relation): self
    {
        return new self(RewriteKind::Computed, relation: RelationName::assert($relation));
    }

    /**
     * Every subject holding `$relation` on an object that the relation `$via` points at: the parent, the space.
     */
    public static function fromRelated(string $via, string $relation): self
    {
        return new self(RewriteKind::FromRelated, relation: RelationName::assert($relation), via: RelationName::assert($via));
    }

    public static function union(self $first, self $second, self ...$others): self
    {
        return new self(RewriteKind::Union, children: [$first, $second, ...array_values($others)]);
    }

    public static function intersection(self $first, self $second, self ...$others): self
    {
        return new self(RewriteKind::Intersection, children: [$first, $second, ...array_values($others)]);
    }

    /**
     * The subjects of `$base` that `$subtract` does not hold: the members, but not the blocked ones.
     */
    public static function exclusion(self $base, self $subtract): self
    {
        return new self(RewriteKind::Exclusion, children: [$base, $subtract]);
    }

    /**
     * The subject types of every direct rewrite inside this one, which a tuple written on the relation must match.
     *
     * @return list<SubjectType>
     */
    public function directTypes(): array
    {
        if (RewriteKind::Direct === $this->kind) {
            return $this->types;
        }

        $types = [];

        foreach ($this->children as $child) {
            array_push($types, ...$child->directTypes());
        }

        return $types;
    }

    /**
     * The relation as text, for an error message or an explanation.
     */
    public function toString(): string
    {
        return match ($this->kind) {
            RewriteKind::Direct => '['.implode(', ', array_map(static fn (SubjectType $type): string => $type->toString(), $this->types)).']',
            RewriteKind::Computed => (string) $this->relation,
            RewriteKind::FromRelated => $this->relation.' from '.$this->via,
            RewriteKind::Union => '('.implode(' or ', $this->childStrings()).')',
            RewriteKind::Intersection => '('.implode(' and ', $this->childStrings()).')',
            RewriteKind::Exclusion => '('.implode(' but not ', $this->childStrings()).')',
        };
    }

    /**
     * @return list<string>
     */
    private function childStrings(): array
    {
        return array_map(static fn (self $child): string => $child->toString(), $this->children);
    }
}
