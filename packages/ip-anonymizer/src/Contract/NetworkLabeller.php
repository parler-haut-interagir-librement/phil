<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Contract;

use Phil\IpAnonymizer\IpAddress;

/**
 * The coarse label of the network an address belongs to — a region, an office, a partner — or `null` when no
 * network it knows holds the address.
 */
interface NetworkLabeller
{
    public function labelOf(IpAddress $address): ?string;
}
