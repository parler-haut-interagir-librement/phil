<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasurementType::class)]
final class MeasurementColumnTypeTest extends TestCase
{
    private MeasurementType $type;

    private SQLitePlatform $platform;

    protected function setUp(): void
    {
        $this->type = new MeasurementType(UnitRegistry::standard());
        $this->platform = new SQLitePlatform();
    }

    public function testTheColumnIsAStringOfOneHundredCharactersByDefault(): void
    {
        self::assertSame('VARCHAR(100)', $this->type->getSQLDeclaration([], $this->platform));
        self::assertSame('VARCHAR(40)', $this->type->getSQLDeclaration(['length' => 40], $this->platform));
        self::assertSame('VARCHAR(100)', $this->type->getSQLDeclaration(['length' => null], $this->platform));
    }

    public function testAMeasurementIsStoredAsItsTextAndReadBack(): void
    {
        $registry = UnitRegistry::standard();
        $weight = $registry->measurement('1', 'kg')->convertTo($registry->unit('[lb_av]'));
        $stored = $this->type->convertToDatabaseValue($weight, $this->platform);

        self::assertSame('2.20462262184877581 [lb_av]', $stored);
        self::assertSame(0, $this->type->convertToPHPValue($stored, $this->platform)?->compareTo($weight));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertSame('12.5 kg', $this->type->convertToPHPValue('12.5 kg', $this->platform)?->toString());
    }

    public function testAnotherValueIsRefused(): void
    {
        $this->expectException(InvalidType::class);
        $this->type->convertToDatabaseValue('12.5 kg', $this->platform);
    }

    public function testTheRefusalNamesTheTypesItExpects(): void
    {
        try {
            $this->type->convertToDatabaseValue('12.5 kg', $this->platform);
            self::fail('A string is not a measurement.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, '.Measurement::class, $invalidType->getMessage());
        }
    }

    public function testAColumnThatIsNotTextIsRefused(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue(12, $this->platform);
    }

    public function testAnUnknownUnitFailsTheHydration(): void
    {
        try {
            $this->type->convertToPHPValue('12 furlong', $this->platform);
            self::fail('An unknown unit is refused.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('The unit "furlong" is not in the registry.', $valueNotConvertible->getMessage());
            self::assertNotNull($valueNotConvertible->getPrevious());
        }
    }
}
