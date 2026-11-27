<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType;
use Phil\MoneyBundle\Infrastructure\Doctrine\MoneyEmbeddable;
use Phil\MoneyBundle\Infrastructure\Doctrine\MoneyMappingDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(MoneyMappingDriver::class)]
final class MoneyMappingDriverTest extends TestCase
{
    public function testMoneyIsTheOnlyClassItMaps(): void
    {
        $driver = new MoneyMappingDriver();

        self::assertSame([Money::class], $driver->getAllClassNames());
        self::assertFalse($driver->isTransient(Money::class));
        self::assertTrue($driver->isTransient(MoneyEmbeddable::class));
        self::assertTrue($driver->isTransient(stdClass::class));
    }

    public function testMoneyIsAnEmbeddableOfAnAmountAndACurrency(): void
    {
        $metadata = $this->metadata(Money::class);
        new MoneyMappingDriver()->loadMetadataForClass(Money::class, $metadata);

        self::assertTrue($metadata->isEmbeddedClass);
        self::assertSame(['minorUnits', 'currency'], $metadata->getFieldNames());

        $amount = $metadata->getFieldMapping('minorUnits');
        self::assertSame('amount', $amount->columnName);
        self::assertSame(Types::BIGINT, $amount->type);
        self::assertNotTrue($amount->nullable);

        $currency = $metadata->getFieldMapping('currency');
        self::assertSame('currency', $currency->columnName);
        self::assertSame(CurrencyType::NAME, $currency->type);
        self::assertNotTrue($currency->nullable);
    }

    public function testAnotherClassIsRefused(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessageIsOrContains(MoneyEmbeddable::class);

        new MoneyMappingDriver()->loadMetadataForClass(MoneyEmbeddable::class, $this->metadata(MoneyEmbeddable::class));
    }

    public function testMetadataOfAnotherMapperIsRefused(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessageIsOrContains(Money::class);

        new MoneyMappingDriver()->loadMetadataForClass(Money::class, self::createStub(PersistenceClassMetadata::class));
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
