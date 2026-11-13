<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use Phil\PollBundle\Domain\Status\CandidateStatus;
use Phil\PollBundle\Domain\Status\TieBreak;

/**
 * One round of a single transferable vote, as it is published: the passes over the ballots it took for the keep
 * factors to settle, the quota of its last pass, every option still in the count in the options' declared order,
 * the value no option kept, the surplus left over the quota, the options elected during the round and those excluded
 * or defeated at its end, and how a tie for the fewest votes was broken, when there was one.
 */
final readonly class ElectionRound
{
    /**
     * @param list<CandidateTally> $tallies
     * @param list<string>         $elected
     * @param list<string>         $excluded
     */
    public function __construct(
        public int $number,
        public int $iterations,
        public Billionths $quota,
        public array $tallies,
        public Billionths $exhausted,
        public Billionths $surplus,
        public array $elected,
        public array $excluded,
        public ?TieBreak $tieBreak = null,
    ) {
    }

    /**
     * The tally of one option, `null` for an option no longer in the count.
     */
    public function tallyOf(string $option): ?CandidateTally
    {
        foreach ($this->tallies as $tally) {
            if ($tally->option === $option) {
                return $tally;
            }
        }

        return null;
    }

    /**
     * The round as plain values, its numbers written with their nine decimals, for a page, a file or a comparison.
     *
     * @return array{number: int, iterations: int, quota: string, tallies: list<array{option: string, votes: string, keep_factor: string, status: string}>, exhausted: string, surplus: string, elected: list<string>, excluded: list<string>, tie_break: ?string}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'iterations' => $this->iterations,
            'quota' => $this->quota->toString(),
            'tallies' => array_map(static fn (CandidateTally $tally): array => [
                'option' => $tally->option,
                'votes' => $tally->votes->toString(),
                'keep_factor' => $tally->keepFactor->toString(),
                'status' => $tally->status->value,
            ], $this->tallies),
            'exhausted' => $this->exhausted->toString(),
            'surplus' => $this->surplus->toString(),
            'elected' => $this->elected,
            'excluded' => $this->excluded,
            'tie_break' => $this->tieBreak?->value,
        ];
    }

    /**
     * Whether the count ends with this round: no option was excluded at its end, or those left out were defeated
     * once the seats were filled.
     */
    public function isLast(): bool
    {
        return [] === $this->excluded || CandidateStatus::Defeated === $this->tallyOf($this->excluded[0])?->status;
    }

    /**
     * Whether the option ends the round elected.
     */
    public function isElected(string $option): bool
    {
        return CandidateStatus::Elected === $this->tallyOf($option)?->status;
    }
}
