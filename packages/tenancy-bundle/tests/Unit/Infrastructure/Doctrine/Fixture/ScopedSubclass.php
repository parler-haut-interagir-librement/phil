<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

/**
 * A subclass that declares the scope its root lacks: the filter, handed the root, would never read it.
 */
#[TenantScoped(field: 'tenantRef')]
final class ScopedSubclass extends InheritanceRoot
{
}
