<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;

/**
 * The choices of a ballot as the indexes of the poll's options, checked: each one an option of the poll, given once.
 *
 * @internal
 */
final class Choices
{
    /**
     * @param array<array-key, int> $index the position of each option, by identifier
     *
     * @return list<int>
     *
     * @throws InvalidBallot
     */
    public static function indexes(Ballot $ballot, array $index): array
    {
        $indexes = [];
        $seen = [];

        foreach ($ballot->choices as $choice) {
            $position = $index[$choice] ?? null;

            if (null === $position) {
                throw InvalidBallot::because(\sprintf('"%s" is not an option of this poll.', $choice));
            }

            if (isset($seen[$position])) {
                throw InvalidBallot::because(\sprintf('The option "%s" is chosen twice.', $choice));
            }

            $seen[$position] = $position;
            $indexes[] = $position;
        }

        return $indexes;
    }

    /**
     * @return array<array-key, int>
     */
    public static function index(PollRules $rules): array
    {
        return array_flip($rules->options);
    }
}
