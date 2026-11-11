<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

use Phil\FootnotesSidenotes\Exception\InvalidOptions;

/**
 * How the number of a note is written: decimal, roman in lower or upper case, or a sequence of symbols.
 *
 * Symbols are doubled, then tripled, once the sequence is spent — `*`, `†`, … `**`, `††` —; past three rounds, as past
 * 3999 in roman numerals, the number is written in decimal, so a label stays short whatever the document.
 */
final readonly class Numbering
{
    /** The classical sequence of reference marks. */
    public const array SYMBOLS = ['*', '†', '‡', '§', '‖', '¶'];

    private const int MAX_ROMAN = 3999;

    private const int MAX_ROUNDS = 3;

    private const array ROMAN = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];

    /**
     * @param list<string> $symbols
     */
    private function __construct(
        private string $style,
        private array $symbols = [],
    ) {
    }

    public static function decimal(): self
    {
        return new self('decimal');
    }

    public static function lowerRoman(): self
    {
        return new self('lower-roman');
    }

    public static function upperRoman(): self
    {
        return new self('upper-roman');
    }

    /**
     * @param list<string> $symbols each of 1 to 8 characters, all different
     *
     * @throws InvalidOptions
     */
    public static function symbols(array $symbols = self::SYMBOLS): self
    {
        if ([] === $symbols) {
            throw InvalidOptions::because('a numbering by symbols needs at least one symbol');
        }

        foreach ($symbols as $symbol) {
            if ('' === $symbol || mb_strlen($symbol) > 8) {
                throw InvalidOptions::because('a symbol holds 1 to 8 characters');
            }
        }

        if (array_unique($symbols) !== $symbols) {
            throw InvalidOptions::because('the symbols must all be different');
        }

        return new self('symbols', $symbols);
    }

    /**
     * The label of a number, from 1.
     */
    public function format(int $number): string
    {
        return match ($this->style) {
            'lower-roman' => strtolower($this->roman($number)),
            'upper-roman' => $this->roman($number),
            'symbols' => $this->symbol($number),
            default => (string) $number,
        };
    }

    private function roman(int $number): string
    {
        if ($number > self::MAX_ROMAN) {
            return (string) $number;
        }

        $written = '';

        foreach (self::ROMAN as $numeral => $value) {
            $written .= str_repeat($numeral, intdiv($number, $value));
            $number %= $value;
        }

        return $written;
    }

    private function symbol(int $number): string
    {
        $count = \count($this->symbols);
        $rounds = intdiv($number - 1, $count) + 1;

        return $rounds > self::MAX_ROUNDS ? (string) $number : str_repeat($this->symbols[($number - 1) % $count], $rounds);
    }
}
