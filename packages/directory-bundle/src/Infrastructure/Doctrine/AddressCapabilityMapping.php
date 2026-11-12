<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\DirectoryBundle\Domain\Capability\AddressCapable;

/**
 * The columns of `AddressCapable`: the postal address, in the JSON column type of `phil/address-bundle`.
 */
final class AddressCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return AddressCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [['fieldName' => 'address', 'type' => 'phil_address', 'columnName' => 'address', 'nullable' => true]];
    }
}
