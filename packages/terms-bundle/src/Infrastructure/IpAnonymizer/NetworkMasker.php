<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\Truncation;
use Phil\TermsBundle\Application\Contract\AddressMasker;

/**
 * The network of the address, through the truncation of `phil/ip-anonymizer` — a /24 for IPv4 and a /48 for IPv6 by
 * default —, never the address itself; nothing for a text that is not an address.
 */
final readonly class NetworkMasker implements AddressMasker
{
    public function __construct(
        private Truncation $truncation,
    ) {
    }

    #[Override]
    public function mask(string $address): ?string
    {
        $parsed = IpAddress::tryParse($address);

        return $parsed instanceof IpAddress ? $this->truncation->anonymize($parsed) : null;
    }
}
