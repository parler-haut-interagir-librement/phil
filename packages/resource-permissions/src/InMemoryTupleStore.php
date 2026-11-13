<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Psr\Clock\ClockInterface;

/**
 * The tuples in an array, for tests and a single process; lost with the object. Reads are ordered by subject — kind,
 * identifier, then relation — or by object identifier, byte by byte.
 */
final class InMemoryTupleStore implements TupleStore
{
    /** @var array<string, RelationTuple> */
    private array $tuples = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function update(array $delete, array $write): void
    {
        foreach ($delete as $tuple) {
            unset($this->tuples[$tuple->key()]);
        }

        foreach ($write as $tuple) {
            $this->tuples[$tuple->key()] = $tuple;
        }
    }

    #[Override]
    public function matching(ResourceReference $object, array $relations, array $subjects): array
    {
        $found = [];

        foreach ($relations as $relation) {
            foreach ($subjects as $subject) {
                $tuple = $this->tuples[new RelationTuple($object, $relation, $subject)->key()] ?? null;

                if (null !== $tuple && !$tuple->hasExpiredAt($this->clock->now())) {
                    $found[] = $tuple;
                }
            }
        }

        usort($found, static fn (RelationTuple $a, RelationTuple $b): int => strcmp($a->relation."\0".self::order($a->subject), $b->relation."\0".self::order($b->subject)));

        return $found;
    }

    #[Override]
    public function subjects(ResourceReference $object, string $relation, int $limit, bool $setsOnly = false): array
    {
        $found = array_filter(
            $this->live(),
            static fn (RelationTuple $tuple): bool => $tuple->object->equals($object)
                && $tuple->relation === $relation
                && (!$setsOnly || $tuple->subject->isSet()),
        );
        usort($found, static fn (RelationTuple $a, RelationTuple $b): int => strcmp(self::order($a->subject), self::order($b->subject)));

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function objects(string $kind, string $relation, Subject $subject, int $limit): array
    {
        $found = array_filter(
            $this->live(),
            static fn (RelationTuple $tuple): bool => $tuple->object->key === $kind
                && $tuple->relation === $relation
                && $tuple->subject->equals($subject),
        );
        usort($found, static fn (RelationTuple $a, RelationTuple $b): int => strcmp($a->object->id, $b->object->id));

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function forget(ResourceReference $object): int
    {
        $before = \count($this->tuples);
        $this->tuples = array_filter(
            $this->tuples,
            static fn (RelationTuple $tuple): bool => !$tuple->object->equals($object) && !$tuple->subject->reference->equals($object),
        );

        return $before - \count($this->tuples);
    }

    #[Override]
    public function purgeExpired(): int
    {
        $before = \count($this->tuples);
        $this->tuples = $this->live();

        return $before - \count($this->tuples);
    }

    /**
     * A subject's kind, identifier and relation, joined by a byte that sorts before any of theirs.
     */
    private static function order(Subject $subject): string
    {
        return $subject->reference->key."\0".$subject->reference->id."\0".($subject->relation ?? '');
    }

    /**
     * @return array<string, RelationTuple>
     */
    private function live(): array
    {
        $now = $this->clock->now();

        return array_filter($this->tuples, static fn (RelationTuple $tuple): bool => !$tuple->hasExpiredAt($now));
    }
}
