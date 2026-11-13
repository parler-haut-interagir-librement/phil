<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * The limits of one check or one listing, against deep, wide or hostile graphs: how many relations deep it follows,
 * how many times it reads the store, how many subject sets or related objects it follows behind one relation, and how
 * many candidates a listing looks at. A cycle is cut on its own and costs nothing more.
 */
final readonly class Budget
{
    public const int MAX = 100_000;

    /** @var positive-int */
    public int $maxDepth;

    /** @var positive-int */
    public int $maxReads;

    /** @var positive-int */
    public int $maxFanOut;

    /** @var positive-int */
    public int $maxCandidates;

    /**
     * @param int $maxDepth      relations followed one inside the other
     * @param int $maxReads      reads of the store
     * @param int $maxFanOut     subject sets or related objects behind one relation
     * @param int $maxCandidates objects a listing looks at before it says it is incomplete
     */
    public function __construct(int $maxDepth = 32, int $maxReads = 500, int $maxFanOut = 200, int $maxCandidates = 1000)
    {
        $this->maxDepth = $this->limit('maxDepth', $maxDepth);
        $this->maxReads = $this->limit('maxReads', $maxReads);
        $this->maxFanOut = $this->limit('maxFanOut', $maxFanOut);
        $this->maxCandidates = $this->limit('maxCandidates', $maxCandidates);
    }

    /**
     * @return positive-int
     */
    private function limit(string $name, int $value): int
    {
        if ($value < 1 || $value > self::MAX) {
            throw InvalidRelation::because(\sprintf("The budget's %s is between 1 and %d; %d is not.", $name, self::MAX, $value));
        }

        return $value;
    }
}
