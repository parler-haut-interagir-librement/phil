<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\View;

use Phil\Poll\NetPromoter;
use Phil\Poll\Rating;

/**
 * What one viewer sees of the results of a poll, when they are theirs to see: the method, the ballots and abstentions
 * counted, the seats, one line per option in the order of the standings — per mark from the lowest, for a scale —, and
 * whether the poll is closed; for a scale, the rating, whose mean is the sum of the marks over the ballots marked, and
 * for a Net Promoter Score, its three groups. A closed poll's results are revealed: a template may celebrate them.
 */
final readonly class ResultsView
{
    /**
     * @param list<ResultLine> $lines
     */
    public function __construct(
        public string $poll,
        public string $question,
        public string $method,
        public int $ballots,
        public int $abstentions,
        public int $seats,
        public array $lines,
        public bool $revealed,
        public ?Rating $rating = null,
        public ?NetPromoter $netPromoter = null,
    ) {
    }

    /**
     * The labels of the options that won or were elected, in the order of the standings.
     *
     * @return list<string>
     */
    public function winners(): array
    {
        return array_values(array_map(static fn (ResultLine $line): string => $line->label, array_filter($this->lines, static fn (ResultLine $line): bool => $line->winner)));
    }
}
