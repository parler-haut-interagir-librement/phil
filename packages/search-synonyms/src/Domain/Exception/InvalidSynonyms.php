<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Domain\Exception;

use InvalidArgumentException;

/**
 * Synonyms refused: a phrase too long, a rule with one side empty, a line of a file that is no rule — with its number.
 */
final class InvalidSynonyms extends InvalidArgumentException
{
    /**
     * @param int|null $fileLine the line of the file, from 1, when the synonyms were read from one
     */
    public function __construct(
        string $message,
        public readonly ?int $fileLine = null,
    ) {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function atLine(int $line, string $reason): self
    {
        return new self(\sprintf('Line %d: %s', $line, $reason), $line);
    }
}
