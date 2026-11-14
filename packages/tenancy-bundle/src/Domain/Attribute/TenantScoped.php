<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Attribute;

use Attribute;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Declares an entity as owned by one tenant, and names the field that holds its tenant reference.
 *
 * The Doctrine filter of this bundle restricts every entity that carries this attribute to the active tenant,
 * and leaves every other entity alone. The field is either a mapped field holding the reference, or a
 * single-column association to the application's tenant entity.
 *
 * The attribute is read on the class the filter receives, which is the root of an inheritance hierarchy: it
 * belongs on the root entity. On a subclass whose root lacks it, the filter refuses the query.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TenantScoped
{
    public function __construct(
        public string $field = 'tenant',
    ) {
        if ('' === $field) {
            throw InvalidValue::because('A tenant-scoped entity must name the field that holds its tenant.');
        }
    }
}
