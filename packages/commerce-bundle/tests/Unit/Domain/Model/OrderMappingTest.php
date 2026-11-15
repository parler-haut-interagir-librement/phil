<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for `phil_commerce_order`: what changes here changes their schema.
 */
#[CoversClass(Order::class)]
final class OrderMappingTest extends TestCase
{
    public function testTheOrderIsOneRowKeyedByItsNumber(): void
    {
        $metadata = $this->metadata();

        self::assertSame('phil_commerce_order', $metadata->getTableName());
        self::assertSame(['number'], $metadata->getIdentifierFieldNames());
        self::assertSame('version', $metadata->versionField);
        self::assertSame([['columns' => ['state', 'placed_at']]], array_values($metadata->table['indexes'] ?? []));
    }

    public function testEachColumnIsMappedAsTheSchemaExpects(): void
    {
        $metadata = $this->metadata();
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame([
            'currencyCode' => ['currency', 'string', 3, false, true],
            'lines' => ['lines', 'phil_commerce_order_lines', null, false, null],
            'adjustments' => ['adjustments', 'phil_commerce_adjustments', null, false, null],
            'taxZone' => ['tax_zone', 'string', 64, true, null],
            'taxIncluded' => ['tax_included', 'boolean', null, false, null],
            'channel' => ['channel', 'string', 64, true, null],
            'customerGroup' => ['customer_group', 'string', 64, true, null],
            'deliveryMethod' => ['delivery_method', 'string', 64, true, null],
            'codes' => ['codes', 'json', null, false, null],
            'state' => ['state', 'string', 16, false, null],
            'placedAt' => ['placed_at', 'datetime_immutable', null, true, null],
            'cancelledAt' => ['cancelled_at', 'datetime_immutable', null, true, null],
            'customer' => ['customer', 'string', 255, true, null],
            'gift' => ['gift', 'json', null, true, null],
            'wishList' => ['wish_list', 'string', 36, true, true],
            'manualDiscounts' => ['manual_discounts', 'json', null, false, null],
            'status' => ['status', 'string', 64, true, null],
            'revision' => ['revision', 'integer', null, false, null],
            'expiredAt' => ['expired_at', 'datetime_immutable', null, true, null],
            'paymentsStarted' => ['payments_started', 'integer', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'number' => ['number', 'string', 64, false, null],
        ], $columns);
        self::assertSame(OrderState::class, $metadata->fieldMappings['state']->enumType);
    }

    /**
     * The longest number an order accepts is the length of its column.
     */
    public function testTheLongestNumberFitsItsColumn(): void
    {
        $longest = Order::open(str_repeat('9', 64), Currency::of('CAD'))->number;

        self::assertSame($this->metadata()->fieldMappings['number']->length, \strlen($longest));
    }

    /**
     * @return ClassMetadata<Order>
     */
    private function metadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(Order::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass(Order::class, $metadata);

        return $metadata;
    }
}
