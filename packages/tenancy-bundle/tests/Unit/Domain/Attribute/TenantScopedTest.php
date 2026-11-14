<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Attribute;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Attribute\TenantScoped;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantScoped::class)]
final class TenantScopedTest extends TestCase
{
    public function testTheFieldDefaultsToTenant(): void
    {
        self::assertSame('tenant', new TenantScoped()->field);
    }

    public function testAnEmptyFieldIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new TenantScoped('');
    }
}
