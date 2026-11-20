<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\TenancyBundle\Infrastructure\Doctrine\EntityTenant;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Owned;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\TenantRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(EntityTenant::class)]
final class EntityTenantTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function fieldValues(): iterable
    {
        yield 'a reference' => ['retail', 'retail'];
        yield 'an integer key' => [7, '7'];
        yield 'a value object' => [new class implements Stringable {
            public function __toString(): string
            {
                return 'wholesale';
            }
        }, 'wholesale'];
        yield 'no tenant' => [null, null];
        yield 'a value that is no reference' => [1.5, null];
        yield 'an object that is no reference' => [new TenantRecord('retail'), null];
    }

    #[DataProvider('fieldValues')]
    public function testTheTenantOfAMappedField(mixed $value, ?string $expected): void
    {
        $metadata = new ClassMetadata(Owned::class);
        $metadata->mapField(['fieldName' => 'tenant', 'type' => 'string']);
        $metadata->wakeupReflection(new RuntimeReflectionService());

        self::assertSame($expected, EntityTenant::of(self::createStub(EntityManagerInterface::class), $metadata, 'tenant', new Owned($value)));
    }

    public function testTheTenantOfAnAssociationIsTheIdentifierOfTheTenantEntity(): void
    {
        $metadata = new ClassMetadata(Owned::class);
        $metadata->mapManyToOne(['fieldName' => 'tenant', 'targetEntity' => TenantRecord::class, 'joinColumns' => [['name' => 'tenant_id', 'referencedColumnName' => 'id']]]);
        $metadata->wakeupReflection(new RuntimeReflectionService());

        $tenant = new TenantRecord('retail');
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('getSingleIdentifierValue')->with($tenant)->willReturn('retail');
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        self::assertSame('retail', EntityTenant::of($manager, $metadata, 'tenant', new Owned($tenant)));
        self::assertNull(EntityTenant::of($manager, $metadata, 'tenant', new Owned()), 'no tenant entity');
    }
}
