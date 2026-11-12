<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Internal;

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\IpFamily;

/**
 * The mask of a prefix length: as many bytes as an address of the family, the first bits set and the others clear.
 * An address ANDed with it keeps its network and loses its host bits.
 *
 * @internal
 */
final readonly class PrefixMask
{
    /**
     * The byte after the full bytes of the prefix, by the number of bits of the prefix it holds.
     */
    private const string PARTIAL_BYTES = "\x00\x80\xC0\xE0\xF0\xF8\xFC\xFE";

    /**
     * @throws InvalidNetwork when the length is outside 0 to 32 for IPv4, or 0 to 128 for IPv6
     */
    public static function of(IpFamily $family, int $length): string
    {
        $bits = $family->bits();

        if ($length < 0 || $length > $bits) {
            throw InvalidNetwork::because(\sprintf('An %s prefix length is from 0 to %d; %d is not.', $family->value, $bits, $length));
        }

        $bytes = intdiv($bits, 8);
        $mask = str_repeat("\xFF", intdiv($length, 8)).self::PARTIAL_BYTES[$length % 8];

        return substr(str_pad($mask, $bytes, "\x00"), 0, $bytes);
    }
}
