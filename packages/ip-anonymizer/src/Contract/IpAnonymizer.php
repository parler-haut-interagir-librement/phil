<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Contract;

use Phil\IpAnonymizer\IpAddress;

/**
 * A strategy that turns an address into the text kept in its place: a truncated address, a keyed digest, a network,
 * a label. What it returns is what a log line, a counter or a database row may hold.
 */
interface IpAnonymizer
{
    public function anonymize(IpAddress $address): string;
}
