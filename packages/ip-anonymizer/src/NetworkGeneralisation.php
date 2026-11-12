<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\Internal\PrefixMask;

/**
 * The network that holds an address, in CIDR notation: `192.0.2.123` becomes `192.0.2.0/24` with the default /24 and
 * /48. Unlike a truncated address, the text says how much was kept.
 */
final readonly class NetworkGeneralisation implements IpAnonymizer
{
    /**
     * @throws InvalidNetwork when a prefix is outside 0 to 32 for IPv4, or 0 to 128 for IPv6
     */
    public function __construct(
        public int $ipv4Prefix = Truncation::DEFAULT_IPV4_PREFIX,
        public int $ipv6Prefix = Truncation::DEFAULT_IPV6_PREFIX,
    ) {
        PrefixMask::of(IpFamily::V4, $ipv4Prefix);
        PrefixMask::of(IpFamily::V6, $ipv6Prefix);
    }

    public function network(IpAddress $address): IpNetwork
    {
        return IpNetwork::containing($address, IpFamily::V4 === $address->family ? $this->ipv4Prefix : $this->ipv6Prefix);
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return $this->network($address)->toString();
    }
}
