<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelOption;
use Symfony\Component\Serializer\Attribute\Context;

/**
 * A request body whose set names its enum on the property, so that the serializer reads it back.
 */
final class ShippingRequest
{
    // No `@var EnumSet<ParcelOption>`: with a PHPDoc reader installed, the serializer would read the set as a
    // collection of cases and hand the property a plain array (see doc/api.md).
    #[Context([EnumsNormalizer::ENUM => ParcelOption::class])]
    public ?EnumSet $options = null; // @phpstan-ignore missingType.generics
}
