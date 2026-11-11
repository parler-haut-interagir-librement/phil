<?php

declare(strict_types=1);

namespace Phil\Poll;

use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\Exception\UnknownCountingMethod;

/**
 * The counting methods of an application, by name, each name declared once.
 */
final readonly class CountingMethods
{
    /** @var array<string, CountingMethod> */
    private array $methods;

    /**
     * @param iterable<CountingMethod> $methods
     *
     * @throws InvalidPoll when a name is not 1 to 64 lowercase ASCII letters, digits or `_`, or is declared twice
     */
    public function __construct(iterable $methods)
    {
        $byName = [];

        foreach ($methods as $method) {
            $name = $method->name();

            if (1 !== preg_match('/^[a-z0-9_]{1,64}$/D', $name)) {
                throw InvalidPoll::because(\sprintf('A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_", not "%s".', $name));
            }

            if (isset($byName[$name])) {
                throw InvalidPoll::because(\sprintf('The counting method "%s" is declared twice.', $name));
            }

            $byName[$name] = $method;
        }

        $this->methods = $byName;
    }

    /**
     * The four methods of the library: the count of choices, the instant runoff, the Borda count and Schulze.
     */
    public static function defaults(): self
    {
        return new self([new ChoiceCount(), new InstantRunoff(), new Borda(), new Schulze()]);
    }

    /**
     * The method a poll of this kind is counted by when it names none: the count of choices for single and multiple
     * ballots, the instant runoff for ranked ones.
     */
    public static function defaultFor(BallotKind $kind): string
    {
        return BallotKind::Ranked === $kind ? InstantRunoff::NAME : ChoiceCount::NAME;
    }

    /**
     * @throws UnknownCountingMethod
     */
    public function get(string $name): CountingMethod
    {
        return $this->methods[$name] ?? throw UnknownCountingMethod::named($name);
    }

    public function has(string $name): bool
    {
        return isset($this->methods[$name]);
    }

    /**
     * The names of the methods that count ballots of this kind, in their declared order.
     *
     * @return list<string>
     */
    public function namesFor(BallotKind $kind): array
    {
        $names = [];

        foreach ($this->methods as $method) {
            if ($method->supports($kind)) {
                $names[] = $method->name();
            }
        }

        return $names;
    }

    /**
     * Counts the ballots of a poll by the named method.
     *
     * @param iterable<Ballot> $ballots
     *
     * @throws UnknownCountingMethod
     * @throws InvalidPoll           when the method does not count the rules' kind of ballot
     * @throws InvalidBallot         when a ballot names a choice that is not one of the rules' options
     */
    public function count(string $name, PollRules $rules, iterable $ballots): Result
    {
        return $this->get($name)->count($rules, $ballots);
    }
}
