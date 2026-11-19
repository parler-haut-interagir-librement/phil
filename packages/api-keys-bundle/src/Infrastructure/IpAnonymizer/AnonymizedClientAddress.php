<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\IpAnonymizer;

use Override;
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\IpAddress;

/**
 * The client's address through a strategy of `phil/ip-anonymizer` — truncated by default, a keyed digest or a
 * network when the configuration names one —; a text that is not an address keeps nothing.
 */
final readonly class AnonymizedClientAddress implements ClientAddressAnonymizer
{
    public function __construct(
        private IpAnonymizer $anonymizer,
    ) {
    }

    #[Override]
    public function anonymize(string $address): ?string
    {
        $parsed = IpAddress::tryParse($address);

        return $parsed instanceof IpAddress ? $this->anonymizer->anonymize($parsed) : null;
    }
}
