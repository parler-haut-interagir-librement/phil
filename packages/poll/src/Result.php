<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * The count of a poll: the method that made it, the ballots counted and the abstentions among them, every option's
 * standing from the first place down, and, for an instant runoff, its rounds.
 */
final readonly class Result
{
    /**
     * @param list<Standing> $standings
     * @param list<Round>    $rounds
     */
    public function __construct(
        public string $method,
        public int $ballots,
        public int $abstentions,
        public array $standings,
        public array $rounds = [],
    ) {
    }

    /**
     * The options in first place, several when they are tied; none while no ballot has chosen anything.
     *
     * @return list<string>
     */
    public function winners(): array
    {
        if ($this->ballots === $this->abstentions) {
            return [];
        }

        $winners = [];

        foreach ($this->standings as $standing) {
            if (1 === $standing->place) {
                $winners[] = $standing->option;
            }
        }

        return $winners;
    }

    /**
     * The standing of one option, `null` for an option the poll does not have.
     */
    public function standingOf(string $option): ?Standing
    {
        foreach ($this->standings as $standing) {
            if ($standing->option === $option) {
                return $standing;
            }
        }

        return null;
    }
}
