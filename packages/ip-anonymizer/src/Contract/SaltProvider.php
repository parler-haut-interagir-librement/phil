<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Contract;

use Phil\IpAnonymizer\Salt;

/**
 * The salt a keyed digest is taken with now. A provider that changes its salt on a cadence makes the digests of one
 * address unlinkable from one period to the next.
 */
interface SaltProvider
{
    public function current(): Salt;
}
