<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Benchmark;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;
use Phil\PollBundle\Application\DefaultPollPolicy;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\Resource\ResourceReference;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * The single transferable vote by Meek's method on ten thousand ranked ballots over ten options, three to ten choices
 * deep and in a few hundred distinct orders, for one seat and for three; and the results of a poll of as many
 * ballots asked twice, counted then read from the cache.
 */
final readonly class ElectionBench
{
    private PollRules $rules;

    /** @var list<Ballot> */
    private array $ballots;

    private Polls $polls;

    private string $poll;

    public function __construct()
    {
        $options = array_map(static fn (int $i): string => 'option-'.$i, range(0, 9));
        $this->rules = PollRules::ranked($options);
        $ballots = [];

        for ($voter = 0; $voter < 10_000; ++$voter) {
            $order = [];

            for ($rank = 0, $depth = 3 + ($voter % 8); $rank < $depth; ++$rank) {
                $order[] = $options[($voter * 3 + $rank * ($voter % 7 + 1)) % 10];
            }

            $ballots[] = Ballot::of(array_values(array_unique($order)));
        }

        $this->ballots = $ballots;

        $this->polls = new Polls(new InMemoryPollRepository(), new InMemoryVoteRepository(), new MockClock(), new DefaultPollPolicy(), new CountingMethods([new MeekStv()]), null, new ArrayAdapter(deepClone: false));
        $creator = ResourceReference::of('user', 'creator');
        $this->poll = $this->polls->create(new PollDraft('Board', array_map(static fn (string $option): PollOption => PollOption::of($option, $option), $options), BallotKind::Ranked, counting: MeekStv::NAME, seats: 3), $creator)->id;

        foreach (\array_slice($ballots, 0, 2_000) as $voter => $ballot) {
            $this->polls->cast($this->poll, ResourceReference::of('user', (string) $voter), $ballot->choices);
        }
    }

    public function benchOneSeat(): void
    {
        new MeekStv()->elect($this->rules, $this->ballots, 1);
    }

    public function benchThreeSeats(): void
    {
        new MeekStv()->elect($this->rules, $this->ballots, 3);
    }

    public function benchResultsFromTheCache(): void
    {
        $this->polls->results($this->poll);
    }
}
