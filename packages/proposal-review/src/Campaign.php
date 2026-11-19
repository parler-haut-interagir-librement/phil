<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Internal\Identifier;

/**
 * One evaluation by a jury — a call for papers, the applications to a course, the entries of a contest —: its dated
 * schedule, its weighted criteria, its review policy, and the last phase the board saw, from which `advance()` knows
 * that the phase changed.
 */
final readonly class Campaign
{
    public const int MAX_CRITERIA = 50;

    public string $id;

    /** @var non-empty-list<Criterion> */
    public array $criteria;

    /**
     * @param list<Criterion> $criteria
     *
     * @throws InvalidInput
     */
    public function __construct(
        string $id,
        public Schedule $schedule,
        array $criteria,
        public ReviewPolicy $policy = new ReviewPolicy(),
        public Phase $observedPhase = Phase::NotStarted,
    ) {
        $this->id = Identifier::check($id, 'campaign');

        if ([] === $criteria || \count($criteria) > self::MAX_CRITERIA) {
            throw InvalidInput::because(\sprintf('A campaign has 1 to %d criteria.', self::MAX_CRITERIA));
        }

        Identifier::unique(array_map(static fn (Criterion $criterion): string => $criterion->id, $criteria), 'criterion');
        $this->criteria = $criteria;
    }

    public function withObservedPhase(Phase $phase): self
    {
        return new self($this->id, $this->schedule, $this->criteria, $this->policy, $phase);
    }

    public function criterion(string $id): ?Criterion
    {
        return array_find($this->criteria, static fn (Criterion $criterion): bool => $criterion->id === $id);
    }
}
