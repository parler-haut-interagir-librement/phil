<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application\Contract;

/**
 * What a token's row keeps of the address it was last used from, in place of the address: a truncated address, a
 * keyed digest, a network. The bundle never keeps the address itself.
 */
interface ClientAddressAnonymizer
{
    /**
     * @return string|null null when the text is not an address, and nothing is kept
     */
    public function anonymize(string $address): ?string;
}
