<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\IpAnonymizer;

use Override;
use Phil\BrowserSecurityBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;
use SensitiveParameter;

/**
 * The client's address through a strategy of `phil/ip-anonymizer`, a truncation to a prefix of each family by
 * default; a text that is not an address keeps nothing.
 */
final readonly class TruncatedClientAddress implements ClientAddressAnonymizer
{
    public function __construct(
        private IpAnonymizer $anonymizer,
    ) {
    }

    #[Override]
    public function anonymize(#[SensitiveParameter] string $address): ?string
    {
        $parsed = IpAddress::tryParse($address);

        return $parsed instanceof IpAddress ? $this->anonymizer->anonymize($parsed) : null;
    }
}
