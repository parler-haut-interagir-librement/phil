<?php

declare(strict_types=1);

namespace Phil\Highlight;

use Phil\Highlight\Exception\InvalidLineRanges;
use Stringable;

/**
 * Lines of a block, by their place in it from 1: single lines and inclusive ranges, written `1,3-5` as code fences and
 * templates do. The ranges are kept sorted and merged, so two equal sets of lines are equal objects.
 */
final readonly class LineRanges implements Stringable
{
    /**
     * The most ranges a text may hold, before merging.
     */
    public const int MAX_RANGES = 256;

    /**
     * The greatest line number.
     */
    public const int MAX_LINE = 1_000_000;

    /**
     * @param list<array{int<1, max>, int<1, max>}> $ranges sorted, disjoint and not touching
     */
    private function __construct(
        private array $ranges,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * The lines of a text such as `1,3-5` or `2, 7 - 9`; an empty text holds no line.
     *
     * @throws InvalidLineRanges
     */
    public static function parse(string $ranges): self
    {
        if ('' === trim($ranges)) {
            return self::none();
        }

        $parts = explode(',', $ranges, self::MAX_RANGES + 1);

        if (\count($parts) > self::MAX_RANGES) {
            throw InvalidLineRanges::tooMany(self::MAX_RANGES);
        }

        $pairs = [];

        foreach ($parts as $part) {
            if (1 !== preg_match('/^[ \t]*(\d{1,7})[ \t]*(?:-[ \t]*(\d{1,7})[ \t]*)?$/D', $part, $match)) {
                throw InvalidLineRanges::syntax($ranges);
            }

            $from = self::line($match[1]);
            $pairs[] = [$from, isset($match[2]) ? self::line($match[2]) : $from];
        }

        // The bounds and the order of each range are checked once, by of().
        return self::of(...$pairs);
    }

    /**
     * The lines of the given ranges, each `[from, to]` from 1, inclusive.
     *
     * @param array{int, int} ...$ranges
     *
     * @throws InvalidLineRanges
     */
    public static function of(array ...$ranges): self
    {
        if (\count($ranges) > self::MAX_RANGES) {
            throw InvalidLineRanges::tooMany(self::MAX_RANGES);
        }

        $pairs = [];

        foreach ($ranges as [$from, $to]) {
            if ($from < 1 || $to < 1) {
                throw InvalidLineRanges::syntax($from.'-'.$to);
            }

            if ($from > self::MAX_LINE || $to > self::MAX_LINE) {
                throw InvalidLineRanges::tooLarge(self::MAX_LINE);
            }

            if ($to < $from) {
                throw InvalidLineRanges::reversed($from, $to);
            }

            $pairs[] = [$from, $to];
        }

        usort($pairs, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($pairs as [$from, $to]) {
            $last = array_key_last($merged);

            if (null !== $last && $from <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $to);

                continue;
            }

            $merged[] = [$from, $to];
        }

        return new self($merged);
    }

    public function contains(int $line): bool
    {
        foreach ($this->ranges as [$from, $to]) {
            if ($line < $from) {
                return false;
            }

            if ($line <= $to) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return [] === $this->ranges;
    }

    /**
     * The ranges, sorted and merged, each `[from, to]`.
     *
     * @return list<array{int<1, max>, int<1, max>}>
     */
    public function toArray(): array
    {
        return $this->ranges;
    }

    /**
     * The text `parse()` reads back: `1,3-5`.
     */
    public function toString(): string
    {
        return implode(',', array_map(
            static fn (array $range): string => implode('-', array_unique($range)),
            $this->ranges,
        ));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * A line number of the text; of() checks its upper bound.
     *
     * @return int<1, max>
     *
     * @throws InvalidLineRanges
     */
    private static function line(string $digits): int
    {
        $line = (int) $digits;

        if ($line < 1) {
            throw InvalidLineRanges::syntax($digits);
        }

        return $line;
    }
}
