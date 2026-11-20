<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantColumn;
use Phil\TenancyBundle\Infrastructure\Exception\MisconfiguredTenantScope;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NotScoped;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantColumn::class)]
#[CoversClass(MisconfiguredTenantScope::class)]
final class TenantColumnTest extends TestCase
{
    public function testTheFieldOfAScopedEntityIsTheOneItsAttributeNames(): void
    {
        $metadata = new ClassMetadata(ScopedByField::class);
        $metadata->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant', 'type' => 'string']);

        self::assertSame('tenantRef', TenantColumn::field($metadata));
        self::assertSame('tenant', TenantColumn::of($metadata));
    }

    public function testAnEntityThatIsNotScopedHasNoField(): void
    {
        self::assertNull(TenantColumn::field(new ClassMetadata(NotScoped::class)));
    }

    /**
     * The field alone, asked by the code that detaches the entities, already refuses a scope that names nothing mapped.
     */
    public function testAScopeThatNamesNothingMappedIsRefusedByTheFieldToo(): void
    {
        $this->expectException(MisconfiguredTenantScope::class);

        TenantColumn::field(new ClassMetadata(ScopedByField::class));
    }
}
