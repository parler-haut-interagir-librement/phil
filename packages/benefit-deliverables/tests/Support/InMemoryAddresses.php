<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use Override;
use Phil\Address\Address;
use Phil\BenefitDeliverables\Contract\DeliveryAddressProvider;

final class InMemoryAddresses implements DeliveryAddressProvider
{
    /** @var list<list<string>> the members of each read */
    public array $reads = [];

    /**
     * @param array<string, Address> $addresses
     */
    public function __construct(private readonly array $addresses = [])
    {
    }

    #[Override]
    public function addressesOf(array $members): array
    {
        $this->reads[] = $members;

        return array_intersect_key($this->addresses, array_flip($members));
    }
}
