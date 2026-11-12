<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Address\Address;

/**
 * @phpstan-require-implements AddressCapable
 */
trait AddressTrait
{
    private ?Address $address = null;

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): void
    {
        $this->address = $address;
    }
}
