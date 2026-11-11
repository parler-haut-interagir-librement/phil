<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Status;

use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * A Bitstring Status List: one bit per credential, set when its status applies, the first index being the leftmost bit
 * of the first byte. It is published GZIP-compressed and encoded in multibase base 64 URL (`u…`).
 *
 * Every list holds at least `MIN_SIZE` bits, so that a list does not tell which credentials were issued, and at most
 * `MAX_SIZE`, which bounds what a decoded list may inflate to.
 */
final readonly class StatusList
{
    public const int MIN_SIZE = 131_072;

    public const int MAX_SIZE = 16_777_216;

    private function __construct(
        private string $bits,
    ) {
    }

    /**
     * A list of the size given, a multiple of 8, with the bits of the indexes given set.
     *
     * @param iterable<int> $set
     */
    public static function of(iterable $set = [], int $size = self::MIN_SIZE): self
    {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE || 0 !== $size % 8) {
            throw InvalidBadge::because(\sprintf('A status list holds from %d to %d bits, a multiple of 8.', self::MIN_SIZE, self::MAX_SIZE));
        }

        $bits = str_repeat("\0", intdiv($size, 8));

        foreach ($set as $index) {
            if ($index < 0 || $index >= $size) {
                throw InvalidBadge::because(\sprintf('The index %d is outside the status list of %d bits.', $index, $size));
            }

            $bits[$index >> 3] = pack('C', \ord($bits[$index >> 3]) | (0x80 >> ($index & 7)));
        }

        return new self($bits);
    }

    /**
     * The list an `encodedList` holds.
     *
     * @throws InvalidCredential
     */
    public static function decode(string $encodedList): self
    {
        if (!str_starts_with($encodedList, 'u') || \strlen($encodedList) > self::MAX_SIZE) {
            throw InvalidCredential::because('An encoded status list is in multibase base 64 URL, starting with "u".');
        }

        $bits = @gzdecode(Multibase::decode($encodedList), intdiv(self::MAX_SIZE, 8));

        if (!\is_string($bits) || \strlen($bits) < intdiv(self::MIN_SIZE, 8) || \strlen($bits) > intdiv(self::MAX_SIZE, 8)) {
            throw InvalidCredential::because(\sprintf('An encoded status list is GZIP-compressed and holds from %d to %d bits.', self::MIN_SIZE, self::MAX_SIZE));
        }

        return new self($bits);
    }

    public function size(): int
    {
        return \strlen($this->bits) * 8;
    }

    /**
     * @throws InvalidCredential
     */
    public function isSet(int $index): bool
    {
        if ($index < 0 || $index >= $this->size()) {
            throw InvalidCredential::because(\sprintf('The index %d is outside the status list of %d bits.', $index, $this->size()));
        }

        return 0 !== (\ord($this->bits[$index >> 3]) & (0x80 >> ($index & 7)));
    }

    /**
     * The list as an `encodedList`.
     */
    public function encode(): string
    {
        return Multibase::base64Url((string) gzencode($this->bits));
    }
}
