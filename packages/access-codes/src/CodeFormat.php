<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use Phil\AccessCodes\Exception\InvalidCode;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Random\Randomizer;

/**
 * What a code looks like: a number of symbols drawn uniformly from an alphabet by a cryptographically secure
 * generator, then a check symbol, shown in groups separated by hyphens.
 *
 * The default is 12 symbols of Crockford's alphabet, 60 bits of entropy, and a check symbol: `7KQ2-M9XD-H4TB-R`. A
 * format carries 40 bits at the least: with the keyed hash of the store and a limit on attempts, guessing one code of
 * a batch of a million at 40 bits takes about a million attempts per hit.
 *
 * The check symbol is the Luhn mod N algorithm over the alphabet: it catches every mistyped symbol and most swaps of
 * two neighbours, so that a code miscopied from a printed card is refused before any lookup, without spending a
 * stored attempt on the store.
 */
final readonly class CodeFormat
{
    public const int MIN_ENTROPY_BITS = 40;

    public const int MAX_LENGTH = 64;

    /** The most characters read from what a person typed, separators included. */
    public const int MAX_INPUT_LENGTH = 256;

    public CodeAlphabet $alphabet;

    /** @var array<int|string, int> the value of each symbol, read once for the check symbol */
    private array $values;

    /** @var array<int|string, int> the digits in base N of each symbol's doubled value, added up */
    private array $doubled;

    /** @var int<1, 64> the random symbols */
    public int $length;

    /** @var int<0, 64> the symbols between two hyphens when a code is shown; 0 shows it in one block */
    public int $groupSize;

    /**
     * @param int  $length      the random symbols, 64 at the most, carrying 40 bits at the least
     * @param bool $checkSymbol whether a check symbol follows them
     * @param int  $groupSize   the symbols between two hyphens when a code is shown; 0 shows it in one block
     *
     * @throws InvalidConfiguration when the length, the entropy or the groups are out of bounds
     */
    public function __construct(
        ?CodeAlphabet $alphabet = null,
        int $length = 12,
        public bool $checkSymbol = true,
        int $groupSize = 4,
    ) {
        $this->alphabet = $alphabet ?? new CodeAlphabet();

        if ($length < 1 || $length > self::MAX_LENGTH) {
            throw InvalidConfiguration::because(\sprintf('A code has 1 to %d random symbols.', self::MAX_LENGTH));
        }

        $this->length = $length;

        if ($this->entropyBits() < self::MIN_ENTROPY_BITS) {
            throw InvalidConfiguration::because(\sprintf('A code carries %d bits of entropy at the least; this format carries %.1f.', self::MIN_ENTROPY_BITS, $this->entropyBits()));
        }

        if ($groupSize < 0 || $groupSize > self::MAX_LENGTH) {
            throw InvalidConfiguration::because(\sprintf('A group of a shown code has 0 to %d symbols.', self::MAX_LENGTH));
        }

        $this->groupSize = $groupSize;
        $size = $this->alphabet->size();
        $this->values = array_flip(str_split($this->alphabet->symbols));
        $this->doubled = array_map(static fn (int $value): int => intdiv(2 * $value, $size) + 2 * $value % $size, $this->values);
    }

    /**
     * The shortest format of the alphabet that carries the bits asked for.
     */
    public static function withEntropy(int $bits, ?CodeAlphabet $alphabet = null, bool $checkSymbol = true, int $groupSize = 4): self
    {
        $alphabet ??= new CodeAlphabet();

        return new self($alphabet, (int) ceil($bits / $alphabet->bitsPerSymbol()), $checkSymbol, $groupSize);
    }

    public function entropyBits(): float
    {
        return $this->length * $this->alphabet->bitsPerSymbol();
    }

    /**
     * The symbols of a whole code, its check symbol included.
     */
    public function codeLength(): int
    {
        return $this->length + ($this->checkSymbol ? 1 : 0);
    }

    /**
     * A new code in its canonical form: the symbols without separators.
     *
     * @return non-empty-string
     */
    public function generate(Randomizer $randomizer): string
    {
        $symbols = $randomizer->getBytesFromString($this->alphabet->symbols, $this->length);

        return $this->checkSymbol ? $symbols.$this->checkSymbolOf($symbols) : $symbols;
    }

    /**
     * The canonical form of what a person typed, read as the alphabet reads it.
     *
     * @return non-empty-string
     *
     * @throws InvalidCode when it is too long, of the wrong length, holds a foreign symbol or fails its check symbol
     */
    public function parse(string $text): string
    {
        if (\strlen($text) > self::MAX_INPUT_LENGTH) {
            throw InvalidCode::tooLong(\strlen($text), self::MAX_INPUT_LENGTH);
        }

        $code = $this->alphabet->normalize(trim($text));

        if ('' === $code || \strlen($code) !== $this->codeLength()) {
            throw InvalidCode::length(\strlen($code), $this->codeLength());
        }

        if (\strlen($code) !== strspn($code, $this->alphabet->symbols)) {
            throw InvalidCode::symbol();
        }

        if ($this->checkSymbol && !hash_equals($this->checkSymbolOf(substr($code, 0, -1)), substr($code, -1))) {
            throw InvalidCode::checkSymbol();
        }

        return $code;
    }

    /**
     * The code as people read it: its symbols in groups separated by hyphens.
     */
    public function display(string $code): string
    {
        if (0 === $this->groupSize) {
            return $code;
        }

        return implode('-', str_split($code, $this->groupSize));
    }

    /**
     * The check symbol of the Luhn mod N algorithm: from the right, every other symbol's value is doubled and its
     * digits in base N are added, then the symbol that brings the sum to a multiple of N is chosen.
     */
    private function checkSymbolOf(string $symbols): string
    {
        $size = $this->alphabet->size();
        $sum = 0;
        $double = true;

        for ($position = \strlen($symbols) - 1; $position >= 0; --$position) {
            $sum += $double ? $this->doubled[$symbols[$position]] : $this->values[$symbols[$position]];
            $double = !$double;
        }

        return $this->alphabet->symbolAt(($size - $sum % $size) % $size);
    }
}
