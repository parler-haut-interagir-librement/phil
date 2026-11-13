<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * The one stored fact of the model: `$subject` holds `$relation` on `$object`, until `$expiresAt` when it is given.
 * A tuple is known by its object, relation and subject; writing it again replaces its expiry.
 */
final readonly class RelationTuple
{
    public string $relation;

    public function __construct(
        public ResourceReference $object,
        string $relation,
        public Subject $subject,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        $this->relation = RelationName::assert($relation);

        if (Subject::EVERY === $object->id) {
            throw InvalidRelation::because('A tuple is about one object, never "*".');
        }

        foreach ([$object->id, $subject->reference->id] as $id) {
            if (1 !== preg_match('//u', $id)) {
                throw InvalidRelation::because('The identifiers of a tuple are UTF-8 text.');
            }
        }
    }

    /**
     * Whether the tuple no longer holds at `$now`, to the second.
     */
    public function hasExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt->getTimestamp() <= $now->getTimestamp();
    }

    /**
     * What a tuple is known by, `object#relation@subject`, without its expiry.
     */
    public function key(): string
    {
        return $this->object->toString().'#'.$this->relation.'@'.$this->subject->toString();
    }
}
