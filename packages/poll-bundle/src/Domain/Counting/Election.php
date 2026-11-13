<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use Phil\Poll\Result;
use Phil\Poll\Standing;

/**
 * The count of a poll with several seats: the method, the seats, the ballots counted and the abstentions among them,
 * the options elected in the order of their election, and every round as it is published.
 *
 * The same ballots counted again give the same rounds, to the last decimal: whoever holds the ballots replays the
 * count and compares `toArray()`.
 */
final readonly class Election
{
    /**
     * @param list<string>        $elected
     * @param list<ElectionRound> $rounds
     * @param list<Standing>      $standings
     */
    public function __construct(
        public string $method,
        public int $seats,
        public int $ballots,
        public int $abstentions,
        public array $elected,
        public array $rounds,
        private array $standings,
    ) {
    }

    /**
     * The election as the result of a poll: the elected options share the first place, each scored by the billionths
     * of a vote it held in the end; the others follow, those left out once the seats were filled by their votes, then
     * the excluded ones, the last excluded first.
     */
    public function toResult(): Result
    {
        return new Result($this->method, $this->ballots, $this->abstentions, $this->standings);
    }

    /**
     * The election as plain values, for a page, a file or a comparison with a count replayed.
     *
     * @return array{method: string, seats: int, ballots: int, abstentions: int, elected: list<string>, rounds: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'seats' => $this->seats,
            'ballots' => $this->ballots,
            'abstentions' => $this->abstentions,
            'elected' => $this->elected,
            'rounds' => array_map(static fn (ElectionRound $round): array => $round->toArray(), $this->rounds),
        ];
    }
}
