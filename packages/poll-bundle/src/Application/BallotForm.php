<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;

/**
 * The choices of a ballot read from the fields the template `@PhilPoll/ballot.html.twig` posts: `choice`, the option
 * of a single ballot; `choices[]`, the options of a multiple one; `ranks[option]`, the rank given to each option of a
 * ranked one, an empty rank leaving the option out. What a browser sends is not trusted: anything else than text is
 * refused, and the list read is checked again by the rules of the poll when it is cast.
 */
final readonly class BallotForm
{
    public const string SINGLE = 'choice';

    public const string MULTIPLE = 'choices';

    public const string RANKED = 'ranks';

    /**
     * The choices, in the order a ballot lists them, from the fields of a posted form — `$request->request->all()`.
     *
     * @param array<mixed> $fields
     *
     * @return list<string>
     *
     * @throws InvalidBallot when a field holds something else than the form sends, or two options share a rank
     */
    public static function choices(PollRules $rules, array $fields): array
    {
        return match ($rules->kind) {
            BallotKind::Single => self::single($fields[self::SINGLE] ?? ''),
            BallotKind::Multiple => self::multiple($fields[self::MULTIPLE] ?? []),
            BallotKind::Ranked => self::ranked($rules, $fields[self::RANKED] ?? []),
        };
    }

    /**
     * @return list<string>
     */
    private static function single(mixed $choice): array
    {
        if (!\is_string($choice)) {
            throw InvalidBallot::because('The choice of a single ballot is one option.');
        }

        return '' === $choice ? [] : [$choice];
    }

    /**
     * @return list<string>
     */
    private static function multiple(mixed $choices): array
    {
        if (!\is_array($choices) || !array_is_list($choices)) {
            throw InvalidBallot::because('The choices of a multiple ballot are a list of options.');
        }

        foreach ($choices as $choice) {
            if (!\is_string($choice)) {
                throw InvalidBallot::because('The choices of a multiple ballot are a list of options.');
            }
        }

        /** @var list<string> $choices */
        return $choices;
    }

    /**
     * The options given a rank, the lowest rank first; ranks need not follow each other.
     *
     * @return list<string>
     */
    private static function ranked(PollRules $rules, mixed $ranks): array
    {
        if (!\is_array($ranks)) {
            throw InvalidBallot::because('The ranks of a ranked ballot are given option by option.');
        }

        $byRank = [];

        foreach ($ranks as $option => $rank) {
            if (!\is_string($rank) || !$rules->has((string) $option)) {
                throw InvalidBallot::because('The ranks of a ranked ballot are given option by option.');
            }

            if ('' === $rank) {
                continue;
            }

            if (1 !== preg_match('/^[1-9]\d{0,2}$/D', $rank)) {
                throw InvalidBallot::because(\sprintf('A rank is a number from 1 to %d.', \count($rules->options)));
            }

            if (isset($byRank[$rank])) {
                throw InvalidBallot::because(\sprintf('Two options share the rank %s.', $rank));
            }

            $byRank[$rank] = (string) $option;
        }

        ksort($byRank, \SORT_NUMERIC);

        return array_values($byRank);
    }
}
