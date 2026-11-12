<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimitBundle\Application\Contract\AddressNormalizer;

/**
 * The client's network instead of its host, through the truncation of `phil/ip-anonymizer`: with a /64 prefix, every
 * address of one IPv6 subscriber shares a key, so that a client cannot escape its limit by changing hosts. A text that
 * is not an address is kept as it is.
 */
final readonly class NetworkAddress implements AddressNormalizer
{
    public function __construct(
        private Truncation $truncation,
    ) {
    }

    #[Override]
    public function normalize(string $address): string
    {
        $parsed = IpAddress::tryParse($address);

        return $parsed instanceof IpAddress ? $this->truncation->anonymize($parsed) : $address;
    }
}
