<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\Contract\NetworkLabeller;

/**
 * The label of the network that holds an address — a region, an office —, else what the fallback anonymizer makes of
 * it: a scope, a truncated address.
 */
final readonly class LabelGeneralisation implements IpAnonymizer
{
    public function __construct(
        private NetworkLabeller $labeller,
        private IpAnonymizer $fallback = new ScopeGeneralisation(),
    ) {
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        return $this->labeller->labelOf($address) ?? $this->fallback->anonymize($address);
    }
}
