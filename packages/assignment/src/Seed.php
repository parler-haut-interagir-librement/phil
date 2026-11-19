<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The seed of the draw that orders the applicants. The same seed and the same problem give the same order and the same
 * result on every machine and PHP version, since the draw runs on PHP's Xoshiro256** engine, whose sequence is fixed.
 * Keep it with the result: it lets anyone run the assignment again and check it.
 */
final readonly class Seed
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidProblem when the seed is empty or longer than 255 bytes
     */
    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > 255) {
            throw InvalidProblem::because('A seed is 1 to 255 bytes.');
        }

        return new self($value);
    }

    /**
     * A seed nobody chose, drawn from the system's secure source: 32 hexadecimal characters to record.
     */
    public static function random(): self
    {
        return new self(new Randomizer()->getBytesFromString('0123456789abcdef', 32));
    }

    /**
     * The items in the order the seed draws, starting from the order given.
     *
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', $this->value, true)))->shuffleArray($items);
    }
}
