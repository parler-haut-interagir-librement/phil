<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Phil\IpAnonymizer\Exception\InvalidIpAddress;
use SensitiveParameter;
use Stringable;

/**
 * An IPv4 or IPv6 address, held as its 4 or 16 bytes in network order.
 *
 * **An IPv4-mapped IPv6 address is its IPv4 address.** A dual-stack socket reports an IPv4 client as
 * `::ffff:192.0.2.1`; the address is read as `192.0.2.1`, so that a client is anonymized the same way whichever socket
 * accepted it. The deprecated IPv4-compatible form (`::192.0.2.1`) stays an IPv6 address.
 *
 * **The text is checked before `inet_pton()` reads it**: at most 45 characters, only hexadecimal digits, `:` and
 * `.`. A zone index (`fe80::1%eth0`), brackets, spaces and NUL bytes are refused, and so is a dotted part with a
 * leading zero (`010.0.0.1`), which some readers take as octal and others as decimal.
 */
final readonly class IpAddress implements Stringable
{
    /**
     * The longest text of an address: `ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255`.
     */
    public const int MAX_LENGTH = 45;

    private const string MAPPED_PREFIX = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xFF";

    private function __construct(
        public string $bytes,
        public IpFamily $family,
    ) {
    }

    /**
     * @throws InvalidIpAddress when the text is not an address
     */
    public static function parse(#[SensitiveParameter] string $text): self
    {
        return self::tryParse($text) ?? throw InvalidIpAddress::because('The text is not an IPv4 or IPv6 address.');
    }

    /**
     * The address the text spells, or `null`: the form for a value that comes from a request or a log line.
     */
    public static function tryParse(#[SensitiveParameter] string $text): ?self
    {
        $length = \strlen($text);

        if ($length > self::MAX_LENGTH || $length !== strspn($text, '0123456789abcdefABCDEF:.')) {
            return null;
        }

        $dotted = substr($text, (int) strrpos($text, ':'));

        if (str_contains($dotted, '.') && 1 === preg_match('/[.:]0\d|^0\d/', $dotted)) {
            return null;
        }

        $bytes = inet_pton($text);

        return false === $bytes ? null : self::fromBytes($bytes);
    }

    /**
     * The address of 4 or 16 bytes in network order, as `inet_pton()` returns them.
     *
     * @throws InvalidIpAddress when the string is neither 4 nor 16 bytes long
     */
    public static function fromBytes(#[SensitiveParameter] string $bytes): self
    {
        return match (\strlen($bytes)) {
            4 => new self($bytes, IpFamily::V4),
            16 => str_starts_with($bytes, self::MAPPED_PREFIX)
                ? new self(substr($bytes, 12), IpFamily::V4)
                : new self($bytes, IpFamily::V6),
            default => throw InvalidIpAddress::because('An address is 4 bytes (IPv4) or 16 bytes (IPv6) long.'),
        };
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    /**
     * The canonical text of RFC 5952, the same on every platform: dotted decimal for IPv4; for IPv6, lowercase
     * hexadecimal groups without leading zeros, the longest run of two or more zero groups — the first of equal runs —
     * written `::`, and no dotted part.
     */
    public function toString(): string
    {
        if (IpFamily::V4 === $this->family) {
            return implode('.', array_map(\ord(...), str_split($this->bytes)));
        }

        $groups = array_map(static fn (string $group): string => '0000' === $group ? '0' : ltrim($group, '0'), str_split(sodium_bin2hex($this->bytes), 4));
        $bestStart = -1;
        $bestLength = 1;
        $start = 0;

        foreach ($groups as $index => $group) {
            if ('0' !== $group) {
                $start = $index + 1;
            } elseif ($index - $start >= $bestLength) {
                $bestStart = $start;
                $bestLength = $index - $start + 1;
            }
        }

        if (-1 === $bestStart) {
            return implode(':', $groups);
        }

        return implode(':', \array_slice($groups, 0, $bestStart)).'::'.implode(':', \array_slice($groups, $bestStart + $bestLength));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
