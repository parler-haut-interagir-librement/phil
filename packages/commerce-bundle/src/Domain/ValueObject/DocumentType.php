<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A type of commercial document of the register — a receipt, a credit note, a packing slip a module declares —,
 * numbered in a series of its own, without gaps: its code names the series, its prefix and digits shape its numbers.
 *
 * Its **visibility** says how far its buyer sees a document of the type, on the three levels of the channels:
 * `Listed` among the buyer's documents, `Unlisted` reached by its direct link only — the link of an email —, or
 * `Hidden`, kept for the store. A type is hidden unless declared otherwise.
 */
final readonly class DocumentType
{
    /** The longest number a type gives: its prefix and its padded sequence. */
    public const int NUMBER_LENGTH = 32;

    private function __construct(
        public string $code,
        public string $prefix,
        public int $digits,
        public Visibility $visibility,
    ) {
    }

    /**
     * @param string $code   up to 16 lowercase letters, digits or "_", starting with a letter: the series' name
     * @param string $prefix printed before each number; up to 16 visible ASCII characters, or none
     * @param int    $digits the sequence is padded with zeros to this many digits, 1 to 18
     */
    public static function of(string $code, string $prefix, int $digits = 8, Visibility $visibility = Visibility::Hidden): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,15}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a document type: up to 16 lowercase letters, digits or "_", starting with a letter.', $code));
        }

        if (1 !== preg_match('/^[\x21-\x7E]{0,16}$/D', $prefix)) {
            throw InvalidValue::because(\sprintf('The prefix of the document type "%s" holds up to 16 visible ASCII characters.', $code));
        }

        if ($digits < 1 || $digits > 18 || \strlen($prefix) + $digits > self::NUMBER_LENGTH) {
            throw InvalidValue::because(\sprintf('The document type "%s" pads to 1 to 18 digits, its numbers at most %d characters long.', $code, self::NUMBER_LENGTH));
        }

        return new self($code, $prefix, $digits, $visibility);
    }

    public function format(int $sequence): string
    {
        return $this->prefix.str_pad((string) $sequence, $this->digits, '0', \STR_PAD_LEFT);
    }
}
