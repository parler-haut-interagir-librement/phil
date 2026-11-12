<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;

/**
 * One anonymizer for IPv4 addresses and another for IPv6 addresses.
 */
final readonly class PerFamily implements IpAnonymizer
{
    public function __construct(
        private IpAnonymizer $ipv4,
        private IpAnonymizer $ipv6,
    ) {
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return (IpFamily::V4 === $address->family ? $this->ipv4 : $this->ipv6)->anonymize($address);
    }
}
