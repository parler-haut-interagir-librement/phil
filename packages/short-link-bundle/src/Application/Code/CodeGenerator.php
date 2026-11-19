<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Code;

use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;
use Random\Randomizer;

/**
 * New codes: a random number up to a maximum, written by the codec. A random number rather than the link's
 * identifier, so that a code tells nothing of the links before or after it and the next code cannot be guessed.
 *
 * The randomizer draws from the system's secure source by default and keeps nothing between calls.
 */
final readonly class CodeGenerator
{
    public const int DEFAULT_MAX_NUMBER = 2 ** 40 - 1;

    /**
     * @throws InvalidCodec when the maximum is below one million, too few codes to draw from at random
     */
    public function __construct(
        private SqidCodec $codec,
        private int $maxNumber = self::DEFAULT_MAX_NUMBER,
        private Randomizer $randomizer = new Randomizer(),
    ) {
        if ($maxNumber < 1_000_000) {
            throw InvalidCodec::because('The largest number of a code is 1000000 or more.');
        }
    }

    public function generate(): string
    {
        return $this->codec->encode([$this->randomizer->getInt(0, $this->maxNumber)]);
    }
}
