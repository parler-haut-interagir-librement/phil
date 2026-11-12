<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\Contract\IpTransformer;
use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\Internal\PrefixMask;

/**
 * Clears the host bits of an address past a prefix of each family: `192.0.2.123` becomes `192.0.2.0` and
 * `2001:db8:1234:5678::1` becomes `2001:db8:1234::` with the default /24 and /48.
 *
 * A truncated address still reads as an address, so that it fits the columns and tools made for one. A /24 leaves
 * 256 possible IPv4 hosts and a /48 an organisation's whole IPv6 allocation; shorter prefixes hide more.
 */
final readonly class Truncation implements IpTransformer, IpAnonymizer
{
    public const int DEFAULT_IPV4_PREFIX = 24;

    public const int DEFAULT_IPV6_PREFIX = 48;

    private string $ipv4Mask;

    private string $ipv6Mask;

    /**
     * @throws InvalidNetwork when a prefix is outside 0 to 32 for IPv4, or 0 to 128 for IPv6
     */
    public function __construct(
        public int $ipv4Prefix = self::DEFAULT_IPV4_PREFIX,
        public int $ipv6Prefix = self::DEFAULT_IPV6_PREFIX,
    ) {
        $this->ipv4Mask = PrefixMask::of(IpFamily::V4, $ipv4Prefix);
        $this->ipv6Mask = PrefixMask::of(IpFamily::V6, $ipv6Prefix);
    }

    #[Override]
    public function transform(IpAddress $address): IpAddress
    {
        return IpAddress::fromBytes($address->bytes & (IpFamily::V4 === $address->family ? $this->ipv4Mask : $this->ipv6Mask));
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return $this->transform($address)->toString();
    }
}
