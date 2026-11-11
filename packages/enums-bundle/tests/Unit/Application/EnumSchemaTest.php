<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Application;

use Phil\EnumsBundle\Application\Schema\EnumSchema;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumSchema::class)]
final class EnumSchemaTest extends TestCase
{
    public function testAStringEnumIsDescribedByItsValuesNamesAndDescriptions(): void
    {
        self::assertSame([
            'type' => 'string',
            'enum' => ['pending', 'in_transit', 'cancelled', 'refunded'],
            'x-enum-varnames' => ['PENDING', 'IN_TRANSIT', 'CANCELLED', 'REFUNDED'],
            'x-enum-descriptions' => ['Waiting for payment.', 'The parcel has left.', 'Cancelled', 'REFUNDED'],
        ], EnumSchema::of(OrderStatus::class));
    }

    public function testAPureEnumIsWrittenByItsNames(): void
    {
        $schema = EnumSchema::of(Shape::class);

        self::assertSame('string', $schema['type']);
        self::assertSame(['Circle', 'BigSquare', 'Blank', 'Éclair'], $schema['enum']);
        self::assertSame(['Round', 'BigSquare', 'Blank', 'Éclair'], $schema['x-enum-descriptions']);
    }

    public function testAnIntegerEnumHasItsDefaultAndLeavesOutItsDeprecatedCases(): void
    {
        self::assertSame([
            'type' => 'integer',
            'enum' => [1, 2],
            'x-enum-varnames' => ['Read', 'Write'],
            'x-enum-descriptions' => ['Read', 'Write'],
            'default' => 1,
        ], EnumSchema::of(Access::class));
    }

    public function testDeprecatedCasesAreKeptWhenAskedFor(): void
    {
        $schema = EnumSchema::of(Access::class, withDeprecated: true);

        self::assertSame([1, 2, 4], $schema['enum']);
        self::assertSame(['Read', 'Write', 'Every right.'], $schema['x-enum-descriptions']);
    }
}
