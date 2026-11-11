<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelOption;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;

/**
 * A parcel as an API shows it — an API Platform resource and a NelmioApiDoc model at once —, with a status and an
 * optional option.
 */
#[ApiResource(operations: [new Get(uriTemplate: '/parcels/{id}')])]
final class ParcelView
{
    public int $id = 0;

    public ParcelStatus $status = ParcelStatus::Ordered;

    public ?ParcelOption $option = null;
}
