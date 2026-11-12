<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;

/**
 * The version of a record or of one of its translations, counted from one.
 *
 * **A published version is never edited.** Editing it forks the next version, and the published one stays byte for
 * byte what was approved or sent. Which event freezes a version is the application's decision; the numbering is not.
 */
final readonly class Version
{
    public const int FIRST = 1;

    private function __construct(
        public int $number,
    ) {
    }

    public static function first(): self
    {
        return new self(self::FIRST);
    }

    /**
     * To read back a stored version. Zero and negative numbers are refused, whatever their source: a version
     * zero would read as "no version yet" in one place and as a real row in another.
     */
    public static function of(int $number): self
    {
        if ($number < self::FIRST) {
            throw InvalidLocaleDimension::because(\sprintf('A version is counted from %d; got %d.', self::FIRST, $number));
        }

        return new self($number);
    }

    public function next(): self
    {
        return new self($this->number + 1);
    }

    public function isAfter(self $other): bool
    {
        return $this->number > $other->number;
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number;
    }
}
