<?php

declare(strict_types=1);

namespace Phil\Passwords\Generator;

use Phil\Passwords\Exception\InvalidPolicy;

/**
 * What a generated password is made of: its length, the character sets it draws from, whether the characters read one
 * for another are left out, and whether it holds at least one character of each set.
 */
final readonly class PasswordPolicy
{
    public const int MIN_LENGTH = 4;

    public const int MAX_LENGTH = 512;

    /** @var list<CharacterSet> */
    public array $sets;

    /**
     * @param array<array-key, CharacterSet> $sets
     *
     * @throws InvalidPolicy when the length is out of bounds or no set is given; the shortest length holds a
     *                       character of each of the four sets
     */
    public function __construct(
        public int $length = 20,
        array $sets = [CharacterSet::Lowercase, CharacterSet::Uppercase, CharacterSet::Digits, CharacterSet::Symbols],
        public bool $excludeAmbiguous = false,
        public bool $requireEachSet = true,
    ) {
        $this->sets = array_values(array_unique($sets, \SORT_REGULAR));

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidPolicy(\sprintf('A generated password has %d to %d characters.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        if ([] === $this->sets) {
            throw new InvalidPolicy('A generated password draws from at least one character set.');
        }
    }

    public function withLength(int $length): self
    {
        return new self($length, $this->sets, $this->excludeAmbiguous, $this->requireEachSet);
    }

    public function withSets(CharacterSet ...$sets): self
    {
        return new self($this->length, $sets, $this->excludeAmbiguous, $this->requireEachSet);
    }

    public function withoutAmbiguous(bool $exclude = true): self
    {
        return new self($this->length, $this->sets, $exclude, $this->requireEachSet);
    }

    public function withEachSetRequired(bool $required = true): self
    {
        return new self($this->length, $this->sets, $this->excludeAmbiguous, $required);
    }

    /**
     * The characters of each set, without the ambiguous ones when they are left out.
     *
     * @return list<string>
     */
    public function alphabets(): array
    {
        return array_map(
            fn (CharacterSet $set): string => $this->excludeAmbiguous ? str_replace(str_split(CharacterSet::AMBIGUOUS), '', $set->characters()) : $set->characters(),
            $this->sets,
        );
    }

    /**
     * The entropy of a password of this policy, in bits: the base-2 logarithm of the number of passwords it allows,
     * each equally likely. With each set required, the passwords that miss a set are not counted.
     */
    public function entropyBits(): float
    {
        $sizes = array_map(strlen(...), $this->alphabets());
        $total = array_sum($sizes);

        if (!$this->requireEachSet) {
            return $this->length * log($total, 2);
        }

        // Inclusion and exclusion over the subsets of sets: each subset counts the passwords drawn from it alone, with
        // a sign that turns for each set left out. Each term is divided by total^length to stay in range.
        $terms = [[0, 1]];

        foreach ($sizes as $size) {
            $next = [];

            foreach ($terms as [$sum, $sign]) {
                $next[] = [$sum + $size, $sign];
                $next[] = [$sum, -$sign];
            }

            $terms = $next;
        }

        $share = 0.0;

        foreach ($terms as [$sum, $sign]) {
            $share += $sign * ($sum / $total) ** $this->length;
        }

        return $this->length * log($total, 2) + log($share, 2);
    }
}
