<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;

/**
 * The state of one check or one listing: what it has spent of its budget, the relations it is inside of, to cut a
 * cycle, how many cycles it cut, and what it already knows. Made for one call and dropped after it, so the checker
 * keeps nothing between requests.
 *
 * @internal
 */
final class Traversal
{
    private int $reads = 0;

    /** @var array<string, true> */
    private array $inside = [];

    private int $cuts = 0;

    /** @var array<string, list<RelationTuple>|null> */
    private array $known = [];

    /** @var array<string, array<string, ResourceReference>> */
    private array $gathered = [];

    private bool $truncated = false;

    public function __construct(
        private readonly Budget $budget,
    ) {
    }

    public function read(): void
    {
        if (++$this->reads > $this->budget->maxReads) {
            throw TraversalBudgetExceeded::reads($this->budget->maxReads);
        }
    }

    public function depth(int $depth): void
    {
        if ($depth > $this->budget->maxDepth) {
            throw TraversalBudgetExceeded::depth($this->budget->maxDepth);
        }
    }

    /**
     * Enters a step; false, and one more cut, when the traversal is already inside it.
     */
    public function enter(string $step): bool
    {
        if (isset($this->inside[$step])) {
            ++$this->cuts;

            return false;
        }

        $this->inside[$step] = true;

        return true;
    }

    public function leave(string $step): void
    {
        unset($this->inside[$step]);
    }

    public function cuts(): int
    {
        return $this->cuts;
    }

    public function knows(string $step): bool
    {
        return \array_key_exists($step, $this->known);
    }

    /**
     * @return list<RelationTuple>|null
     */
    public function known(string $step): ?array
    {
        return $this->known[$step] ?? null;
    }

    /**
     * Keeps a step's answer. An answer found while a cycle was cut short is kept only when it is a yes: a no might
     * come from the cut, and be a yes from elsewhere.
     *
     * @param list<RelationTuple>|null $answer
     */
    public function remember(string $step, ?array $answer, int $cutsBefore): void
    {
        if (null !== $answer || $this->cuts === $cutsBefore) {
            $this->known[$step] = $answer;
        }
    }

    /**
     * What earlier rounds of a listing gathered for a step.
     *
     * @return array<string, ResourceReference>
     */
    public function gathered(string $step): array
    {
        return $this->gathered[$step] ?? [];
    }

    /**
     * @param array<string, ResourceReference> $found
     */
    public function keepGathered(string $step, array $found): void
    {
        $this->gathered[$step] = $this->merge($this->gathered[$step] ?? [], $found);
    }

    public function gatheredCount(): int
    {
        return array_sum(array_map(\count(...), $this->gathered));
    }

    /**
     * Adds `$more` to `$found`, keyed by reference, up to the budget's candidates; past them the listing is marked
     * truncated and the rest is dropped.
     *
     * @param array<string, ResourceReference>    $found
     * @param array<array-key, ResourceReference> $more
     *
     * @return array<string, ResourceReference>
     */
    public function merge(array $found, array $more): array
    {
        foreach ($more as $object) {
            $key = $object->toString();

            if (isset($found[$key])) {
                continue;
            }

            if (\count($found) >= $this->budget->maxCandidates) {
                $this->truncated = true;

                return $found;
            }

            $found[$key] = $object;
        }

        return $found;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }

    /**
     * @return positive-int
     */
    public function fanOut(): int
    {
        return $this->budget->maxFanOut;
    }

    /**
     * @return positive-int
     */
    public function candidates(): int
    {
        return $this->budget->maxCandidates;
    }
}
