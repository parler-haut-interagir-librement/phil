<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The results of a set of dice written one character per die, as a production order shows them: `1` to `9`, then `A`
 * for 10 and on through the alphabet to `K` for 20 — a d6 in digits, a d10 in hexadecimal, a d20 past it.
 *
 * A draw reads either in that form, one character per die, or as whole numbers separated by spaces, commas,
 * semicolons or hyphens: `3 5 2 6 1 4`, `10,2`; the one result of a single die reads either way, `17` or `H`.
 */
final readonly class DiceCode
{
    /** The largest die a character writes. */
    public const int MAX_SIDES = 20;

    private const string ALPHABET = '123456789ABCDEFGHIJK';

    /**
     * @param non-empty-list<int<1, 20>> $results
     */
    private function __construct(
        public array $results,
    ) {
    }

    /**
     * The results of dice of these sides, read from a draw.
     *
     * @param non-empty-list<int> $sides the number of faces of each die, in order, from 2 to 20
     *
     * @throws InvalidValue for a draw that does not give one result per die, each from 1 to its die's sides
     */
    public static function read(string $draw, array $sides): self
    {
        foreach ($sides as $side) {
            if ($side < 2 || $side > self::MAX_SIDES) {
                throw InvalidValue::because(\sprintf('A die has 2 to %d faces, not %d.', self::MAX_SIDES, $side));
            }
        }

        $trimmed = trim($draw);
        $numbers = 1 === preg_match('/^[0-9]{1,2}(?:[\s,;-]+[0-9]{1,2})*$/D', $trimmed);
        $results = $numbers && (1 === \count($sides) || 1 === preg_match('/[\s,;-]/', $trimmed))
            ? self::numbers($trimmed)
            : self::characters($trimmed, \count($sides));

        if (\count($results) !== \count($sides)) {
            throw InvalidValue::because(\sprintf('The draw "%s" gives %d results for %d dice.', $draw, \count($results), \count($sides)));
        }

        $checked = [];

        foreach ($results as $index => $result) {
            if ($result < 1 || $result > $sides[$index]) {
                throw InvalidValue::because(\sprintf('The draw "%s" gives %d on die %d, a d%d.', $draw, $result, $index + 1, $sides[$index]));
            }

            $checked[] = $result;
        }

        return new self($checked);
    }

    /**
     * One character per die: `352614`, `A2`, `K`.
     */
    public function code(): string
    {
        return implode('', array_map(static fn (int $result): string => self::ALPHABET[$result - 1], $this->results));
    }

    /**
     * @return list<int>
     */
    private static function numbers(string $draw): array
    {
        preg_match_all('/[0-9]+/', $draw, $numbers);

        return array_map(intval(...), $numbers[0]);
    }

    /**
     * @return list<int>
     */
    private static function characters(string $draw, int $dice): array
    {
        if (\strlen($draw) !== $dice || 1 !== preg_match('/^[1-9A-Ka-k]+$/D', $draw)) {
            return [];
        }

        return array_map(static fn (string $character): int => (int) strpos(self::ALPHABET, strtoupper($character)) + 1, str_split($draw));
    }
}
