<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Phil\TenancyBundle\Domain\Attribute\TenantScoped;

#[TenantScoped]
final class ScopedByAssociation
{
}
