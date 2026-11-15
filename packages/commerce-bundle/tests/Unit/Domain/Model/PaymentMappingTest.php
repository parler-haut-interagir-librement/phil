<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for `phil_commerce_payment`.
 */
#[CoversClass(Payment::class)]
final class PaymentMappingTest extends TestCase
{
    public function testThePaymentIsOneRowKeyedByItsId(): void
    {
        $metadata = $this->metadata();

        self::assertSame('phil_commerce_payment', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('version', $metadata->versionField);
        self::assertSame([['columns' => ['order_number']]], array_values($metadata->table['indexes'] ?? []));
        self::assertSame([['columns' => ['method', 'external_reference']]], array_values($metadata->table['uniqueConstraints'] ?? []));
    }

    public function testEachColumnIsMappedAsTheSchemaExpects(): void
    {
        $metadata = $this->metadata();
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame([
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'refundedMinorUnits' => ['refunded', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'state' => ['state', 'string', 16, false, null],
            'externalReference' => ['external_reference', 'string', 255, true, null],
            'appliedNotifications' => ['applied_notifications', 'json', null, false, null],
            'offline' => ['offline', 'boolean', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'method' => ['method', 'string', 32, false, null],
            'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
        ], $columns);
        self::assertSame(PaymentState::class, $metadata->fieldMappings['state']->enumType);
    }

    /**
     * The id, the longest order number and the longest method fit their columns.
     */
    public function testWhatAPaymentHoldsFitsItsColumns(): void
    {
        $cad = Money::of(1, Currency::of('CAD'));
        $order = Order::open(str_repeat('9', 64), Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'x'), 'X', $cad, 'goods'), 1, $cad);
        $order->place(new DateTimeImmutable());

        $payment = Payment::create($order, 'a'.str_repeat('b', 31), $cad, new DateTimeImmutable());
        $mappings = $this->metadata()->fieldMappings;

        self::assertSame($mappings['id']->length, \strlen($payment->id));
        self::assertSame($mappings['orderNumber']->length, \strlen($payment->orderNumber));
        self::assertSame($mappings['method']->length, \strlen($payment->method));
    }

    /**
     * @return ClassMetadata<Payment>
     */
    private function metadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(Payment::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass(Payment::class, $metadata);

        return $metadata;
    }
}
