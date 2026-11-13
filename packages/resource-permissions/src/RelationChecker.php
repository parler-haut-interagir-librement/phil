<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\RewriteKind;
use Phil\ResourcePermissions\Model\SubjectType;

/**
 * Answers from the tuples of a store and the rewrites of a model.
 *
 * A check walks the rewrite of the relation: the tuples written on it — the subject itself, every object of its kind,
 * or a subject set whose relation is checked in turn —, another relation of the object, the relation of the objects a
 * direct relation points at, and the unions, intersections and exclusions of those. Each step costs from the budget;
 * a step already being walked is a cycle and answers no. An exclusion whose subtracted side met a cycle answers no as
 * well: a no that may come from the cut never grants access.
 *
 * A listing walks the same rewrites backwards from the subject to gather candidates — at most the budget's
 * candidates —, then checks each one, so that it never lists an object that `check()` would refuse.
 *
 * Nothing is kept between calls.
 */
final readonly class RelationChecker implements RelationCheck
{
    public const int MAX_LIMIT = 1000;

    public function __construct(
        private Model $model,
        private TupleStore $store,
        private Budget $budget = new Budget(),
    ) {
    }

    #[Override]
    public function check(ResourceReference|Subject $subject, string $relation, ResourceReference $object): bool
    {
        return $this->explain($subject, $relation, $object) instanceof Explanation;
    }

    #[Override]
    public function explain(ResourceReference|Subject $subject, string $relation, ResourceReference $object): ?Explanation
    {
        $tuples = $this->evaluate(new Traversal($this->budget), $this->subject($subject), $relation, $object, 0);

        return null === $tuples ? null : new Explanation($tuples);
    }

    #[Override]
    public function objects(ResourceReference|Subject $subject, string $relation, string $kind, int $limit = 100): ObjectList
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidRelation::because(\sprintf('A listing asks for 1 to %d objects.', self::MAX_LIMIT));
        }

        $subject = $this->subject($subject);
        $traversal = new Traversal($this->budget);

        do {
            $cuts = $traversal->cuts();
            $before = $traversal->gatheredCount();
            $candidates = $this->gather($traversal, $subject, $kind, $relation, 0);
        } while ($traversal->cuts() !== $cuts && $traversal->gatheredCount() !== $before && !$traversal->truncated());

        $complete = !$traversal->truncated();
        ksort($candidates, \SORT_STRING);
        $objects = [];

        foreach ($candidates as $candidate) {
            if (\count($objects) === $limit) {
                return new ObjectList($objects, false);
            }

            if (null !== $this->evaluate(new Traversal($this->budget), $subject, $relation, $candidate, 0)) {
                $objects[] = $candidate;
            }
        }

        return new ObjectList($objects, $complete);
    }

    private function subject(ResourceReference|Subject $subject): Subject
    {
        return $subject instanceof Subject ? $subject : Subject::of($subject);
    }

    /**
     * @return list<RelationTuple>|null
     */
    private function evaluate(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, int $depth): ?array
    {
        $traversal->depth($depth);
        $step = $object->toString().'#'.$relation;

        if ($traversal->knows($step)) {
            return $traversal->known($step);
        }

        $rewrite = $this->model->rewrite($object->key, $relation);

        if (!$traversal->enter($step)) {
            return null;
        }

        $cuts = $traversal->cuts();

        try {
            $answer = $this->rewrite($traversal, $subject, $relation, $object, $rewrite, $depth);
        } finally {
            $traversal->leave($step);
        }

        $traversal->remember($step, $answer, $cuts);

        return $answer;
    }

    /**
     * @return list<RelationTuple>|null
     */
    private function rewrite(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, Rewrite $rewrite, int $depth): ?array
    {
        return match ($rewrite->kind) {
            RewriteKind::Direct => $this->direct($traversal, $subject, $relation, $object, $depth),
            RewriteKind::Computed => $this->evaluate($traversal, $subject, (string) $rewrite->relation, $object, $depth + 1),
            RewriteKind::FromRelated => $this->fromRelated($traversal, $subject, (string) $rewrite->via, (string) $rewrite->relation, $object, $depth),
            RewriteKind::Union => $this->union($traversal, $subject, $relation, $object, $rewrite->children, $depth),
            RewriteKind::Intersection => $this->intersection($traversal, $subject, $relation, $object, $rewrite->children, $depth),
            RewriteKind::Exclusion => $this->exclusion($traversal, $subject, $relation, $object, $rewrite->children, $depth),
        };
    }

    /**
     * @return list<RelationTuple>|null
     */
    private function direct(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, int $depth): ?array
    {
        $candidates = $subject->isSet() || $subject->isEvery() ? [$subject] : [$subject, Subject::every($subject->reference->key)];
        $traversal->read();

        foreach ($this->store->matching($object, [$relation], $candidates) as $tuple) {
            if ($tuple->subject->reaches($subject)) {
                return [$tuple];
            }
        }

        foreach ($this->follow($traversal, $object, $relation, true) as $tuple) {
            $set = $tuple->subject;
            $through = $this->evaluate($traversal, $subject, (string) $set->relation, $set->reference, $depth + 1);

            if (null !== $through) {
                return [$tuple, ...$through];
            }
        }

        return null;
    }

    /**
     * @return list<RelationTuple>|null
     */
    private function fromRelated(Traversal $traversal, Subject $subject, string $via, string $relation, ResourceReference $object, int $depth): ?array
    {
        foreach ($this->follow($traversal, $object, $via, false) as $tuple) {
            $related = $tuple->subject->reference;

            if ($tuple->subject->isSet() || $tuple->subject->isEvery() || !$this->model->hasRelation($related->key, $relation)) {
                continue;
            }

            $through = $this->evaluate($traversal, $subject, $relation, $related, $depth + 1);

            if (null !== $through) {
                return [$tuple, ...$through];
            }
        }

        return null;
    }

    /**
     * @param list<Rewrite> $children
     *
     * @return list<RelationTuple>|null
     */
    private function union(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, array $children, int $depth): ?array
    {
        foreach ($children as $child) {
            $answer = $this->rewrite($traversal, $subject, $relation, $object, $child, $depth);

            if (null !== $answer) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * @param list<Rewrite> $children
     *
     * @return list<RelationTuple>|null
     */
    private function intersection(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, array $children, int $depth): ?array
    {
        $tuples = [];

        foreach ($children as $child) {
            $answer = $this->rewrite($traversal, $subject, $relation, $object, $child, $depth);

            if (null === $answer) {
                return null;
            }

            array_push($tuples, ...$answer);
        }

        return $tuples;
    }

    /**
     * @param list<Rewrite> $children
     *
     * @return list<RelationTuple>|null
     */
    private function exclusion(Traversal $traversal, Subject $subject, string $relation, ResourceReference $object, array $children, int $depth): ?array
    {
        [$base, $subtract] = $children;
        $answer = $this->rewrite($traversal, $subject, $relation, $object, $base, $depth);

        if (null === $answer) {
            return null;
        }

        $cuts = $traversal->cuts();

        if (null !== $this->rewrite($traversal, $subject, $relation, $object, $subtract, $depth) || $traversal->cuts() !== $cuts) {
            return null;
        }

        return $answer;
    }

    /**
     * The tuples to follow behind a relation, at most the budget's fan-out.
     *
     * @return list<RelationTuple>
     */
    private function follow(Traversal $traversal, ResourceReference $object, string $relation, bool $setsOnly): array
    {
        $traversal->read();
        $tuples = $this->store->subjects($object, $relation, $traversal->fanOut() + 1, $setsOnly);

        if (\count($tuples) > $traversal->fanOut()) {
            throw TraversalBudgetExceeded::fanOut($relation, $traversal->fanOut());
        }

        return $tuples;
    }

    /**
     * The objects of `$kind` on which the rewrite of `$relation` might give `$subject` the relation. A step already
     * being gathered gives what an earlier round found for it; `objects()` repeats the rounds until none adds anything.
     *
     * @return array<string, ResourceReference>
     */
    private function gather(Traversal $traversal, Subject $subject, string $kind, string $relation, int $depth): array
    {
        $traversal->depth($depth);
        $step = $kind.'#'.$relation;

        if (!$traversal->enter($step)) {
            return $traversal->gathered($step);
        }

        try {
            $found = $this->gatherRewrite($traversal, $subject, $kind, $relation, $this->model->rewrite($kind, $relation), $depth);
        } finally {
            $traversal->leave($step);
        }

        $traversal->keepGathered($step, $found);

        return $found;
    }

    /**
     * @return array<string, ResourceReference>
     */
    private function gatherRewrite(Traversal $traversal, Subject $subject, string $kind, string $relation, Rewrite $rewrite, int $depth): array
    {
        switch ($rewrite->kind) {
            case RewriteKind::Direct:
                return $this->gatherDirect($traversal, $subject, $kind, $relation, $rewrite->types, $depth);

            case RewriteKind::Computed:
                return $this->gather($traversal, $subject, $kind, (string) $rewrite->relation, $depth + 1);

            case RewriteKind::FromRelated:
                return $this->gatherFromRelated($traversal, $subject, $kind, (string) $rewrite->via, (string) $rewrite->relation, $depth);

            case RewriteKind::Union:
                $found = [];

                foreach ($rewrite->children as $child) {
                    $found = $traversal->merge($found, $this->gatherRewrite($traversal, $subject, $kind, $relation, $child, $depth));
                }

                return $found;

            default:
                // An intersection or an exclusion holds only objects of its first operand; the checks sort them out.
                return $this->gatherRewrite($traversal, $subject, $kind, $relation, $rewrite->children[0], $depth);
        }
    }

    /**
     * @param list<SubjectType> $types
     *
     * @return array<string, ResourceReference>
     */
    private function gatherDirect(Traversal $traversal, Subject $subject, string $kind, string $relation, array $types, int $depth): array
    {
        $holders = [$subject];

        foreach ($types as $type) {
            if ($type->every && !$subject->isSet() && !$subject->isEvery() && $type->kind === $subject->reference->key) {
                $holders[] = Subject::every($type->kind);
            }
        }

        foreach ($types as $type) {
            if (null !== $type->relation) {
                foreach ($this->gather($traversal, $subject, $type->kind, $type->relation, $depth + 1) as $set) {
                    $holders[] = Subject::set($set, $type->relation);
                }
            }
        }

        return $this->collect($traversal, $kind, $relation, $holders);
    }

    /**
     * @return array<string, ResourceReference>
     */
    private function gatherFromRelated(Traversal $traversal, Subject $subject, string $kind, string $via, string $relation, int $depth): array
    {
        $holders = [];

        foreach ($this->model->rewrite($kind, $via)->types as $type) {
            if (null === $type->relation && !$type->every && $this->model->hasRelation($type->kind, $relation)) {
                foreach ($this->gather($traversal, $subject, $type->kind, $relation, $depth + 1) as $related) {
                    $holders[] = Subject::of($related);
                }
            }
        }

        return $this->collect($traversal, $kind, $via, $holders);
    }

    /**
     * The objects of `$kind` whose `$relation` is given to one of `$holders`, at most the budget's candidates.
     *
     * @param list<Subject> $holders
     *
     * @return array<string, ResourceReference>
     */
    private function collect(Traversal $traversal, string $kind, string $relation, array $holders): array
    {
        $found = [];

        foreach ($holders as $holder) {
            $traversal->read();
            $tuples = $this->store->objects($kind, $relation, $holder, $traversal->candidates() + 1);
            $found = $traversal->merge($found, array_map(static fn (RelationTuple $tuple): ResourceReference => $tuple->object, $tuples));
        }

        return $found;
    }
}
