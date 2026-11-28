<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

use Phil\Address\Address;

/**
 * Where to ship to each member, read from where the application keeps postal addresses: a subscription's delivery
 * address, a member's profile, a form.
 *
 * The application implements it; the package keeps no address of its own.
 */
interface DeliveryAddressProvider
{
    /**
     * @param list<string> $members at most 1000
     *
     * @return array<string, Address> by member, those with an address only
     */
    public function addressesOf(array $members): array;
}
