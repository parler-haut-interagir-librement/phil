<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * Who a relation is given to: one object — `member:01J…` —, every object of a kind — `member:*` —, or the set of the
 * subjects that hold a relation on an object — `group:editors#member`.
 *
 * A subject's identifier holds no `#`, and `*` alone only for every object of a kind, so that `toString()` and
 * `fromString()` read each other back.
 */
final readonly class Subject
{
    public const string EVERY = '*';

    private function __construct(
        public ResourceReference $reference,
        public ?string $relation,
    ) {
    }

    public static function of(ResourceReference $reference): self
    {
        self::assertIdentifier($reference->id);

        return new self($reference, null);
    }

    /**
     * The subjects that hold `$relation` on `$reference`.
     */
    public static function set(ResourceReference $reference, string $relation): self
    {
        self::assertIdentifier($reference->id);

        return new self($reference, RelationName::assert($relation));
    }

    /**
     * Every object of a kind: the subject that grants a relation to all of them at once.
     */
    public static function every(string $kind): self
    {
        try {
            return new self(ResourceReference::of($kind, self::EVERY), null);
        } catch (InvalidResource $invalidResource) {
            throw InvalidRelation::because($invalidResource->getMessage());
        }
    }

    /**
     * The subject `toString()` wrote: `kind:id`, `kind:id#relation` or `kind:*`.
     */
    public static function fromString(string $subject): self
    {
        $hash = strrpos($subject, '#');
        $reference = false === $hash ? $subject : substr($subject, 0, $hash);

        try {
            $resource = ResourceReference::fromString($reference);
        } catch (InvalidResource $invalidResource) {
            throw InvalidRelation::because($invalidResource->getMessage());
        }

        if (false !== $hash) {
            return self::set($resource, substr($subject, $hash + 1));
        }

        return self::EVERY === $resource->id ? new self($resource, null) : self::of($resource);
    }

    public function isSet(): bool
    {
        return null !== $this->relation;
    }

    public function isEvery(): bool
    {
        return self::EVERY === $this->reference->id;
    }

    /**
     * Whether a tuple given to `$this` reaches `$subject`: the same subject, or every object of the kind of a subject
     * that is one object.
     */
    public function reaches(self $subject): bool
    {
        if ($this->equals($subject)) {
            return true;
        }

        return $this->isEvery() && !$subject->isSet() && $subject->reference->key === $this->reference->key;
    }

    public function equals(self $other): bool
    {
        return $this->reference->equals($other->reference) && $this->relation === $other->relation;
    }

    public function toString(): string
    {
        return $this->reference->toString().(null === $this->relation ? '' : '#'.$this->relation);
    }

    private static function assertIdentifier(string $id): void
    {
        if (str_contains($id, '#')) {
            throw InvalidRelation::because('A subject identifier holds no "#".');
        }

        if (self::EVERY === $id) {
            throw InvalidRelation::because('A subject identifier is never "*" alone: Subject::every() names every object of a kind.');
        }
    }
}
