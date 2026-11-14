<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Exception;

use LogicException;

/**
 * A tenant scope the filter cannot apply: a field it cannot turn into a single column, or an attribute on a
 * subclass of an inheritance hierarchy whose root does not carry it. This is a mapping error, raised rather
 * than ignored: an entity the filter skips would be visible across every tenant.
 */
final class MisconfiguredTenantScope extends LogicException
{
    public static function forField(string $entity, string $field): self
    {
        return new self(\sprintf('The tenant-scoped entity "%s" names the field "%s", which is neither a mapped field nor a single-column association.', $entity, $field));
    }

    public static function onSubclass(string $root, string $subclass): self
    {
        return new self(\sprintf('The entity "%s" is tenant-scoped but its inheritance root "%s" is not: the filter reads the scope on the root, so it belongs there.', $subclass, $root));
    }
}
