<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\ValueObject;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Random\Randomizer;
use SensitiveParameter;
use Stringable;

/**
 * A gift card's code: sixteen characters of Crockford's Base32 — digits and letters, without I, L, O and U —, shown
 * in four groups, `7KQ2-M9XA-3TFD-W8HC`. Eighty bits of chance: guessing one is out of reach, as long as the
 * application limits how often a code may be tried.
 *
 * Reading forgives what a person types: lowercase, spaces, hyphens, and the letters I, L and O read as 1, 1 and 0.
 * Only the code's hash is stored, and its last four characters, to tell cards apart.
 */
final readonly class GiftCardCode implements Stringable
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int LENGTH = 16;

    private function __construct(
        #[SensitiveParameter]
        private string $value,
    ) {
    }

    public static function generate(?Randomizer $randomizer = null): self
    {
        return new self(($randomizer ?? new Randomizer())->getBytesFromString(self::ALPHABET, self::LENGTH));
    }

    public static function fromString(#[SensitiveParameter] string $code): self
    {
        $value = strtr(strtoupper(str_replace(['-', ' '], '', $code)), 'ILO', '110');

        if (1 !== preg_match('/^[0-9A-HJKMNP-TV-Z]{16}$/D', $value)) {
            throw InvalidValue::because('A gift card code is sixteen digits and letters.');
        }

        return new self($value);
    }

    /**
     * The code as printed on the card, in four groups.
     */
    public function toString(): string
    {
        return implode('-', str_split($this->value, 4));
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * What is stored instead of the code.
     */
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }

    public function lastFour(): string
    {
        return substr($this->value, -4);
    }

    /**
     * @return array{lastFour: string}
     */
    public function __debugInfo(): array
    {
        return ['lastFour' => $this->lastFour()];
    }
}
