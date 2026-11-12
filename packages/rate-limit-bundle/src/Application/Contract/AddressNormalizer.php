<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Contract;

/**
 * What the client's address is turned into before it is hashed: the address itself by default, its network with
 * `phil/ip-anonymizer`, so that the hosts of one network share a key.
 */
interface AddressNormalizer
{
    public function normalize(string $address): string;
}
