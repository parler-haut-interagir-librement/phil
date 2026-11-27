<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Doctrine\CoordinatesEmbeddable;
use Phil\GeoBundle\Infrastructure\Doctrine\CoordinatesMappingDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CoordinatesMappingDriver::class)]
final class CoordinatesMappingDriverTest extends TestCase
{
    public function testCoordinatesIsTheOnlyClassItMaps(): void
    {
        $driver = new CoordinatesMappingDriver();

        self::assertSame([Coordinates::class], $driver->getAllClassNames());
        self::assertFalse($driver->isTransient(Coordinates::class));
        self::assertTrue($driver->isTransient(CoordinatesEmbeddable::class));
        self::assertTrue($driver->isTransient(stdClass::class));
    }

    public function testCoordinatesIsAnEmbeddableOfTwoFloatColumns(): void
    {
        $metadata = $this->metadata(Coordinates::class);
        new CoordinatesMappingDriver()->loadMetadataForClass(Coordinates::class, $metadata);

        self::assertTrue($metadata->isEmbeddedClass);
        self::assertSame(['latitude', 'longitude'], $metadata->getFieldNames());

        foreach (['latitude', 'longitude'] as $field) {
            $mapping = $metadata->getFieldMapping($field);
            self::assertSame($field, $mapping->columnName);
            self::assertSame(Types::FLOAT, $mapping->type);
            self::assertNotTrue($mapping->nullable);
        }
    }

    public function testAnotherClassIsRefused(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessageIsOrContains(CoordinatesEmbeddable::class);

        new CoordinatesMappingDriver()->loadMetadataForClass(CoordinatesEmbeddable::class, $this->metadata(CoordinatesEmbeddable::class));
    }

    public function testMetadataOfAnotherMapperIsRefused(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessageIsOrContains(Coordinates::class);

        new CoordinatesMappingDriver()->loadMetadataForClass(Coordinates::class, self::createStub(PersistenceClassMetadata::class));
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        return $metadata;
    }
}
