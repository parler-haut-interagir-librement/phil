<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use Phil\AccessCodes\Exception\InvalidConfiguration;

/**
 * The symbols a code is written with: 16 to 36 distinct digits and capital letters of ASCII.
 *
 * The default is the alphabet of Douglas Crockford's Base32, `0123456789ABCDEFGHJKMNPQRSTVWXYZ`: no `I`, `L` or `O`,
 * which a reader takes for `1` and `0`, and no `U`. When a code is read, the letters people mistype for a symbol of
 * the alphabet are taken for it — `O` for `0`, `I` and `L` for `1` —, each only when the letter is outside the
 * alphabet and the digit inside it.
 */
final readonly class CodeAlphabet
{
    public const string CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const int MIN_SIZE = 16;

    public const int MAX_SIZE = 36;

    private const array LOOKALIKES = ['O' => '0', 'I' => '1', 'L' => '1'];

    /** @var non-empty-string */
    public string $symbols;

    /** @var array<string, string> the lookalike letters taken for a symbol of this alphabet */
    private array $lookalikes;

    /**
     * @throws InvalidConfiguration when the symbols are not 16 to 36 distinct digits and capital letters
     */
    public function __construct(string $symbols = self::CROCKFORD)
    {
        if (1 !== preg_match('/^[0-9A-Z]{16,36}$/D', $symbols)) {
            throw InvalidConfiguration::because(\sprintf('An alphabet of codes holds %d to %d digits and capital letters.', self::MIN_SIZE, self::MAX_SIZE));
        }

        if (\count(array_unique(str_split($symbols))) !== \strlen($symbols)) {
            throw InvalidConfiguration::because('An alphabet of codes holds each symbol once.');
        }

        $lookalikes = [];

        foreach (self::LOOKALIKES as $letter => $digit) {
            if (!str_contains($symbols, $letter) && str_contains($symbols, $digit)) {
                $lookalikes[$letter] = $digit;
            }
        }

        $this->symbols = $symbols;
        $this->lookalikes = $lookalikes;
    }

    public function size(): int
    {
        return \strlen($this->symbols);
    }

    /**
     * The bits of entropy one symbol drawn uniformly carries: 5 for Crockford's 32 symbols.
     */
    public function bitsPerSymbol(): float
    {
        return log($this->size(), 2);
    }

    /**
     * The position of a symbol in the alphabet, or `null` for a character outside it.
     */
    public function indexOf(string $symbol): ?int
    {
        $index = '' === $symbol ? false : strpos($this->symbols, $symbol);

        return false === $index ? null : $index;
    }

    public function symbolAt(int $index): string
    {
        return $this->symbols[$index];
    }

    /**
     * The text as the alphabet reads it: in capitals, without the hyphens and spaces people group a code with, the
     * lookalike letters taken for their digit.
     */
    public function normalize(string $text): string
    {
        return strtr(str_replace(['-', ' '], '', strtoupper($text)), $this->lookalikes);
    }
}
