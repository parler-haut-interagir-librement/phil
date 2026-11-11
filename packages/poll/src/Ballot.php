<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * The choices of one voter, in the order of preference for a ranked ballot; none for an abstention.
 *
 * `PollRules::ballot()` builds a ballot checked against the rules of its poll; `of()` builds one as given, for a
 * store that reads back ballots it checked when they were cast. A count checks again that every choice is one of the
 * poll's options, given once.
 */
final readonly class Ballot
{
    /**
     * @param list<string> $choices
     */
    private function __construct(
        public array $choices,
    ) {
    }

    /**
     * @param list<string> $choices
     */
    public static function of(array $choices): self
    {
        return new self($choices);
    }

    public function isAbstention(): bool
    {
        return [] === $this->choices;
    }
}
