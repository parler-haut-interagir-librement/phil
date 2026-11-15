<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\CommerceBundle\Domain\Deferred\Fulfilment;
use Phil\CommerceBundle\Domain\Deferred\StockCommitment;
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for `phil_commerce_deferred_sale`: what changes here changes their
 * schema.
 */
#[CoversClass(DeferredSale::class)]
final class DeferredSaleMappingTest extends TestCase
{
    public function testTheSaleIsOneRowKeyedByItsOrder(): void
    {
        $metadata = $this->metadata();

        self::assertSame('phil_commerce_deferred_sale', $metadata->getTableName());
        self::assertSame(['orderNumber'], $metadata->getIdentifierFieldNames());
        self::assertSame('version', $metadata->versionField);
        self::assertSame([['columns' => ['state', 'due_at']]], array_values($metadata->table['indexes'] ?? []));
    }

    /**
     * A sale is opened first: the mapping is read from the constructor that a sale goes through.
     */
    public function testEachColumnIsMappedAsTheSchemaExpects(): void
    {
        $sale = $this->sale();
        $metadata = $this->metadata();
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->enumType];
        }

        self::assertSame([
            'state' => ['state', 'string', 16, false, DeferredSaleState::class],
            'stockCommitted' => ['stock_committed', 'boolean', null, false, null],
            'depositPaidAt' => ['deposit_paid_at', 'datetime_immutable', null, true, null],
            'balanceCalledAt' => ['balance_called_at', 'datetime_immutable', null, true, null],
            'closedAt' => ['closed_at', 'datetime_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'trigger' => ['balance_trigger', 'string', 16, false, BalanceTrigger::class],
            'commitment' => ['stock_commitment', 'string', 16, false, StockCommitment::class],
            'fulfilment' => ['fulfilment', 'string', 16, false, Fulfilment::class],
            'depositMinorUnits' => ['deposit', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, null],
            'openedAt' => ['opened_at', 'datetime_immutable', null, false, null],
            'dueAt' => ['due_at', 'datetime_immutable', null, true, null],
        ], $columns);
        self::assertTrue($metadata->fieldMappings['currencyCode']->options['fixed'] ?? false);
        self::assertSame($metadata->fieldMappings['orderNumber']->length, \strlen($sale->orderNumber));
        self::assertNull($sale->dueAt);
    }

    private function sale(): DeferredSale
    {
        $cad = Money::of(1, Currency::of('CAD'));
        $order = Order::open(str_repeat('9', 64), Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'x'), 'X', $cad, 'goods'), 1, $cad);
        $order->place(new DateTimeImmutable('2026-11-11 10:00:00'));

        return DeferredSale::open($order, DeferredSalePolicy::preorder(Deposit::share(2_500)), new DateTimeImmutable('2026-11-11 10:00:00'))[0];
    }

    /**
     * @return ClassMetadata<DeferredSale>
     */
    private function metadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(DeferredSale::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass(DeferredSale::class, $metadata);

        return $metadata;
    }
}
