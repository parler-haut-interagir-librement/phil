<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Contract;

/**
 * The address of a client as the bundle may keep it: truncated, digested or generalised, never raw. The bundle's
 * adapter reads a strategy of `phil/ip-anonymizer`; without one, no address is kept.
 */
interface ClientAddressAnonymizer
{
    /**
     * The anonymized address, or `null` when the text is not an address.
     */
    public function anonymize(string $address): ?string;
}
