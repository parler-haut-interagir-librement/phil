<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Model\Model;

/**
 * Writes tuples that the model accepts: the object's kind declares the relation, and one of the relation's direct
 * rewrites accepts the subject's type. A relation computed only from others takes no tuple.
 */
final readonly class RelationWriter
{
    public const int MAX_TUPLES = 1000;

    public function __construct(
        private Model $model,
        private TupleStore $store,
    ) {
    }

    /**
     * Gives `$subject` the relation on `$object`, until `$expiresAt` when it is given; giving it again replaces the
     * expiry.
     */
    public function grant(ResourceReference $object, string $relation, ResourceReference|Subject $subject, ?DateTimeImmutable $expiresAt = null): RelationTuple
    {
        $tuple = new RelationTuple($object, $relation, $subject instanceof Subject ? $subject : Subject::of($subject), $expiresAt);
        $this->update([], [$tuple]);

        return $tuple;
    }

    public function revoke(ResourceReference $object, string $relation, ResourceReference|Subject $subject): void
    {
        $this->update([new RelationTuple($object, $relation, $subject instanceof Subject ? $subject : Subject::of($subject))], []);
    }

    /**
     * Deletes `$revoke` and writes `$grant` at once, each checked against the model first.
     *
     * @param list<RelationTuple> $revoke
     * @param list<RelationTuple> $grant
     */
    public function update(array $revoke, array $grant): void
    {
        if (\count($revoke) + \count($grant) > self::MAX_TUPLES) {
            throw InvalidRelation::because(\sprintf('An update writes or deletes at most %d tuples.', self::MAX_TUPLES));
        }

        foreach ($grant as $tuple) {
            if (!$this->model->accepts($tuple->object->key, $tuple->relation, $tuple->subject)) {
                throw InvalidRelation::because(\sprintf('The model does not accept %s on %s#%s.', $tuple->subject->toString(), $tuple->object->key, $tuple->relation));
            }
        }

        foreach ($revoke as $tuple) {
            $this->model->rewrite($tuple->object->key, $tuple->relation);
        }

        $this->store->update($revoke, $grant);
    }

    /**
     * Deletes every tuple on `$object` and given to it.
     */
    public function forget(ResourceReference $object): int
    {
        return $this->store->forget($object);
    }
}
