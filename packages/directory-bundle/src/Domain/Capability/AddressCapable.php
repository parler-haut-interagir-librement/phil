<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Address\Address;

/**
 * A row that holds where its place is: a postal address of `phil/address`, shared by every locale.
 */
interface AddressCapable
{
    public function getAddress(): ?Address;

    public function setAddress(?Address $address): void;
}
