<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Model;

use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnknownRelation;
use Phil\ResourcePermissions\Subject;

/**
 * The kinds of objects and their relations, checked once when the model is built: every kind a rewrite names is
 * declared, every relation it names exists, and the relation a `fromRelated()` follows is a direct one, so that a
 * check and a listing only ever meet what the model declares.
 */
final readonly class Model
{
    /** @var array<string, TypeDefinition> */
    private array $types;

    public function __construct(TypeDefinition ...$types)
    {
        $byKind = [];

        foreach ($types as $type) {
            if (isset($byKind[$type->kind])) {
                throw InvalidRelation::because(\sprintf('The kind "%s" is declared twice.', $type->kind));
            }

            $byKind[$type->kind] = $type;
        }

        $this->types = $byKind;

        foreach ($byKind as $type) {
            foreach ($type->relations as $relation => $rewrite) {
                $this->validate($type, $relation, $rewrite);
            }
        }
    }

    public function type(string $kind): TypeDefinition
    {
        return $this->types[$kind] ?? throw UnknownRelation::kind($kind);
    }

    public function hasRelation(string $kind, string $relation): bool
    {
        return isset($this->types[$kind]) && $this->types[$kind]->has($relation);
    }

    public function rewrite(string $kind, string $relation): Rewrite
    {
        return $this->type($kind)->rewrite($relation);
    }

    /**
     * Whether a tuple of `$relation` on an object of `$kind` may be given to `$subject`: the relation has a direct
     * rewrite that accepts the subject's type.
     */
    public function accepts(string $kind, string $relation, Subject $subject): bool
    {
        return array_any(
            $this->rewrite($kind, $relation)->directTypes(),
            static fn (SubjectType $type): bool => $type->accepts($subject),
        );
    }

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        return array_keys($this->types);
    }

    private function validate(TypeDefinition $type, string $relation, Rewrite $rewrite): void
    {
        $where = \sprintf('%s#%s', $type->kind, $relation);

        switch ($rewrite->kind) {
            case RewriteKind::Direct:
                foreach ($rewrite->types as $subjectType) {
                    $this->assertKind($where, $subjectType->kind);

                    if (null !== $subjectType->relation && !$this->types[$subjectType->kind]->has($subjectType->relation)) {
                        throw InvalidRelation::because(\sprintf('%s accepts %s, which the model does not declare.', $where, $subjectType->toString()));
                    }
                }

                return;

            case RewriteKind::Computed:
                if (!$type->has((string) $rewrite->relation)) {
                    throw InvalidRelation::because(\sprintf('%s reads the relation "%s", which "%s" does not declare.', $where, $rewrite->relation, $type->kind));
                }

                return;

            case RewriteKind::FromRelated:
                $this->validateFromRelated($type, $where, (string) $rewrite->via, (string) $rewrite->relation);

                return;

            default:
                foreach ($rewrite->children as $child) {
                    $this->validate($type, $relation, $child);
                }
        }
    }

    private function validateFromRelated(TypeDefinition $type, string $where, string $via, string $relation): void
    {
        if (!$type->has($via) || RewriteKind::Direct !== $type->rewrite($via)->kind) {
            throw InvalidRelation::because(\sprintf('%s follows "%s", which must be a direct relation of "%s".', $where, $via, $type->kind));
        }

        $targets = array_filter(
            $type->rewrite($via)->types,
            static fn (SubjectType $subjectType): bool => null === $subjectType->relation && !$subjectType->every,
        );

        if (!array_any($targets, fn (SubjectType $target): bool => isset($this->types[$target->kind]) && $this->types[$target->kind]->has($relation))) {
            throw InvalidRelation::because(\sprintf('%s reads "%s" on the objects of "%s", none of which declares it.', $where, $relation, $via));
        }
    }

    private function assertKind(string $where, string $kind): void
    {
        if (!isset($this->types[$kind])) {
            throw InvalidRelation::because(\sprintf('%s names the kind "%s", which the model does not declare.', $where, $kind));
        }
    }
}
