<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\View;

/**
 * What one viewer sees of the results of a poll, when they are theirs to see: the method, the ballots and abstentions
 * counted, the seats, one line per option in the order of the standings, and whether the poll is closed. A closed
 * poll's results are revealed: a template may celebrate them.
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
