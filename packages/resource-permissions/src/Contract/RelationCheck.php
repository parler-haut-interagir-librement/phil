<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Contract;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\Explanation;
use Phil\ResourcePermissions\ObjectList;
use Phil\ResourcePermissions\Subject;

/**
 * The questions the model answers: does a subject hold a relation on an object, why, and on which objects.
 */
interface RelationCheck
{
    /**
     * @throws TraversalBudgetExceeded when the answer would take more than the budget
     */
    public function check(ResourceReference|Subject $subject, string $relation, ResourceReference $object): bool;

    /**
     * The chain of tuples that gives `$subject` the relation, or null when nothing does.
     *
     * @throws TraversalBudgetExceeded when the answer would take more than the budget
     */
    public function explain(ResourceReference|Subject $subject, string $relation, ResourceReference $object): ?Explanation;

    /**
     * The objects of `$kind` on which `$subject` holds `$relation`, ordered by identifier, at most `$limit`.
     *
     * @param int $limit from 1 to 1000
     *
     * @throws TraversalBudgetExceeded when the listing would take more than the budget
     */
    public function objects(ResourceReference|Subject $subject, string $relation, string $kind, int $limit = 100): ObjectList;
}
