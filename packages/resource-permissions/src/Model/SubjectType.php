<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Model;

use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\RelationName;
use Phil\ResourcePermissions\Subject;

/**
 * A kind of subject a direct relation accepts: `member` (one member), `member:*` (every member), `group#member` (the
 * members of a group).
 */
final readonly class SubjectType
{
    private function __construct(
        public string $kind,
        public ?string $relation,
        public bool $every,
    ) {
    }

    public static function fromString(string $type): self
    {
        if (str_ends_with($type, ':*')) {
            return new self(self::kind(substr($type, 0, -2)), null, true);
        }

        $parts = explode('#', $type, 2);

        if (2 === \count($parts)) {
            return new self(self::kind($parts[0]), RelationName::assert($parts[1]), false);
        }

        return new self(self::kind($type), null, false);
    }

    public function accepts(Subject $subject): bool
    {
        return $subject->reference->key === $this->kind
            && $subject->relation === $this->relation
            && $subject->isEvery() === $this->every;
    }

    public function toString(): string
    {
        return $this->kind.($this->every ? ':*' : '').(null === $this->relation ? '' : '#'.$this->relation);
    }

    private static function kind(string $kind): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $kind)) {
            throw InvalidRelation::because(\sprintf('A subject type names a kind of 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" does not.', $kind));
        }

        return $kind;
    }
}
