<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Contract;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;

/**
 * Where the tuples are kept. A store never returns a tuple that has expired, by its own clock, and every read is
 * bounded by the limit it is given; it does not check tuples against a model, which `RelationWriter` does.
 */
interface TupleStore
{
    /**
     * Deletes `$delete` and writes `$write` at once: all of it or nothing. A tuple written again replaces the expiry
     * of the one there; deleting a tuple that is not there does nothing.
     *
     * @param list<RelationTuple> $delete
     * @param list<RelationTuple> $write
     */
    public function update(array $delete, array $write): void;

    /**
     * The tuples on `$object` whose relation is one of `$relations` and whose subject is one of `$subjects`, exactly.
     *
     * @param non-empty-list<string>  $relations
     * @param non-empty-list<Subject> $subjects
     *
     * @return list<RelationTuple>
     */
    public function matching(ResourceReference $object, array $relations, array $subjects): array;

    /**
     * The tuples of `$relation` on `$object`, ordered by subject, at most `$limit`; only those given to a subject set
     * when `$setsOnly`.
     *
     * @param positive-int $limit
     *
     * @return list<RelationTuple>
     */
    public function subjects(ResourceReference $object, string $relation, int $limit, bool $setsOnly = false): array;

    /**
     * The tuples of `$relation` given to `$subject` exactly, on objects of `$kind`, ordered by object, at most `$limit`.
     *
     * @param positive-int $limit
     *
     * @return list<RelationTuple>
     */
    public function objects(string $kind, string $relation, Subject $subject, int $limit): array;

    /**
     * Deletes every tuple on `$object` and every tuple given to it, as a subject or a subject set: what a deleted
     * resource or an erased member leaves. Returns how many tuples were deleted.
     */
    public function forget(ResourceReference $object): int;

    /**
     * Deletes the tuples that have expired. Returns how many.
     */
    public function purgeExpired(): int;
}
