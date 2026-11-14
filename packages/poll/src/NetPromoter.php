<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * The marks of a Net Promoter Score, from 0 to 10, in three groups: the promoters gave 9 or 10, the passives 7 or 8,
 * the detractors 0 to 6. The score is the share of promoters less the share of detractors, from -100 to 100.
 */
final readonly class NetPromoter
{
    public function __construct(
        public int $promoters,
        public int $passives,
        public int $detractors,
    ) {
    }

    /**
     * The ballots that gave a mark.
     */
    public function respondents(): int
    {
        return $this->promoters + $this->passives + $this->detractors;
    }

    /**
     * The score, from -100 to 100, `null` while no ballot gave a mark.
     */
    public function score(): ?float
    {
        $respondents = $this->respondents();

        return 0 === $respondents ? null : 100 * ($this->promoters - $this->detractors) / $respondents;
    }
}
