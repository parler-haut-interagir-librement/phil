<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;

/**
 * The scope of an address as its label: `public`, `private`, `loopback`… See `AddressScope`.
 */
final readonly class ScopeGeneralisation implements IpAnonymizer
{
    public function __construct(
        private ScopeClassifier $classifier = new ScopeClassifier(),
    ) {
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return $this->classifier->scopeOf($address)->value;
    }
}
