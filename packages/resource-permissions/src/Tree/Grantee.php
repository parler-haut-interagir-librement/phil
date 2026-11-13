<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tree;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Subject;

/**
 * Who an entry of a node is for: a role, or everyone else — every member none of whose roles has an entry on that
 * node.
 */
final readonly class Grantee
{
    /** The kind under which "everyone else" is kept, as `everyone:*`. */
    public const string EVERYONE = 'everyone';

    private function __construct(
        public ?ResourceReference $role,
    ) {
    }

    public static function role(ResourceReference $role): self
    {
        Subject::of($role);

        return new self($role);
    }

    public static function everyoneElse(): self
    {
        return new self(null);
    }

    public static function fromSubject(Subject $subject): self
    {
        return $subject->isEvery() && self::EVERYONE === $subject->reference->key ? self::everyoneElse() : self::role($subject->reference);
    }

    public function isEveryoneElse(): bool
    {
        return !$this->role instanceof ResourceReference;
    }

    public function subject(): Subject
    {
        return $this->role instanceof ResourceReference ? Subject::of($this->role) : Subject::every(self::EVERYONE);
    }
}
