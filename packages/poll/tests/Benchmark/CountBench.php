<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Benchmark;

use Phil\Poll\Ballot;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\PollRules;

/**
 * A count reads every ballot of a poll: ten thousand ballots over ten options, single for the count of choices and
 * ranked, three to ten choices deep and in a few hundred distinct orders, for the other methods; and the check of one
 * ballot as a request gives it.
 */
final readonly class CountBench
{
    private PollRules $single;

    private PollRules $ranked;

    /** @var list<Ballot> */
    private array $singles;

    /** @var list<Ballot> */
    private array $rankings;

    public function __construct()
    {
        $options = array_map(static fn (int $i): string => 'option-'.$i, range(0, 9));
        $this->single = PollRules::single($options);
        $this->ranked = PollRules::ranked($options);
        $singles = [];
        $rankings = [];

        for ($voter = 0; $voter < 10_000; ++$voter) {
            $singles[] = Ballot::of([$options[($voter * 7) % 10]]);
            $order = [];

            for ($rank = 0, $depth = 3 + ($voter % 8); $rank < $depth; ++$rank) {
                $order[] = $options[($voter * 3 + $rank * ($voter % 7 + 1)) % 10];
            }

            $rankings[] = Ballot::of(array_values(array_unique($order)));
        }

        $this->singles = $singles;
        $this->rankings = $rankings;
    }

    public function benchChoiceCount(): void
    {
        new ChoiceCount()->count($this->single, $this->singles);
    }

    public function benchInstantRunoff(): void
    {
        new InstantRunoff()->count($this->ranked, $this->rankings);
    }

    public function benchBorda(): void
    {
        new Borda()->count($this->ranked, $this->rankings);
    }

    public function benchSchulze(): void
    {
        new Schulze()->count($this->ranked, $this->rankings);
    }

    public function benchBallotChecked(): void
    {
        $this->ranked->ballot(['option-3', 'option-1', 'option-7']);
    }
}
