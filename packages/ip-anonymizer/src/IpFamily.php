<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

/**
 * The two address families, by the length of their addresses.
 */
enum IpFamily: string
{
    case V4 = 'ipv4';
    case V6 = 'ipv6';

    /**
     * The length of an address of the family, in bits: the longest prefix it accepts.
     */
    public function bits(): int
    {
        return match ($this) {
            self::V4 => 32,
            self::V6 => 128,
        };
    }
}
