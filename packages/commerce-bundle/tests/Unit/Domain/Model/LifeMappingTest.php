<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Model\WishList;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the tables of the order's life.
 */
#[CoversClass(OrderChange::class)]
#[CoversClass(OrderStateChange::class)]
#[CoversClass(PaymentLink::class)]
#[CoversClass(Refund::class)]
#[CoversClass(WishList::class)]
final class LifeMappingTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, Closure(): object, string, array<string, array{string, string, int|null, bool, bool|null}>}>
     */
    public static function tables(): iterable
    {
        yield 'OrderChange' => [OrderChange::class, static fn (): object => OrderChange::prepare(self::order(), [OrderChangeLine::of(SellableReference::of('product', 'dice'), 2)], 'Phone call', 'clerk-1', new DateTimeImmutable(), self::cad(2_000)), 'phil_commerce_order_change', [
            'state' => ['state', 'string', 16, false, null],
            'revision' => ['revision', 'integer', null, true, null],
            'confirmedBy' => ['confirmed_by', 'string', 255, true, null],
            'settledAt' => ['settled_at', 'datetime_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'baseRevision' => ['base_revision', 'integer', null, false, null],
            'lines' => ['lines', 'json', null, false, null],
            'reason' => ['reason', 'string', 255, false, null],
            'preparedBy' => ['prepared_by', 'string', 255, false, null],
            'preparedAt' => ['prepared_at', 'datetime_immutable', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'totalBeforeMinorUnits' => ['total_before', 'bigint', null, false, null],
            'totalAfterMinorUnits' => ['total_after', 'bigint', null, false, null],
        ]];
        yield 'OrderStateChange' => [OrderStateChange::class, static fn (): object => OrderStateChange::transition('2026-0001', OrderState::Cart, OrderState::Placed, 'place', 'clerk-1', new DateTimeImmutable()), 'phil_commerce_order_state_change', [
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'from' => ['from_state', 'string', 16, false, null],
            'to' => ['to_state', 'string', 16, false, null],
            'transition' => ['transition', 'string', 32, true, null],
            'actor' => ['actor', 'string', 255, true, null],
            'reason' => ['reason', 'string', 255, true, null],
            'occurredAt' => ['occurred_at', 'datetime_immutable', null, false, null],
        ]];
        yield 'PaymentLink' => [PaymentLink::class, static fn (): object => PaymentLink::create('2026-0001', str_repeat('a', 64), new DateTimeImmutable('2026-11-11'), new DateTimeImmutable('2026-11-12')), 'phil_commerce_payment_link', [
            'revokedAt' => ['revoked_at', 'datetime_immutable', null, true, null],
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'tokenHash' => ['token_hash', 'string', 64, false, true],
            'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
            'expiresAt' => ['expires_at', 'datetime_immutable', null, false, null],
        ]];
        yield 'Refund' => [Refund::class, static fn (): object => Refund::grant(Payment::create(self::order(), 'card', self::cad(1_000), new DateTimeImmutable()), self::cad(100), 'Broken die', 'clerk-1', new DateTimeImmutable()), 'phil_commerce_refund', [
            'state' => ['state', 'string', 16, false, null],
            'executedBy' => ['executed_by', 'string', 255, true, null],
            'settledAt' => ['settled_at', 'datetime_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'paymentId' => ['payment_id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'reason' => ['reason', 'string', 255, false, null],
            'grantedBy' => ['granted_by', 'string', 255, false, null],
            'grantedAt' => ['granted_at', 'datetime_immutable', null, false, null],
        ]];
        yield 'WishList' => [WishList::class, static fn (): object => WishList::create('member-1', 'Birthday', new DateTimeImmutable()), 'phil_commerce_wish_list', [
            'items' => ['items', 'json', null, false, null],
            'shareHash' => ['share_hash', 'string', 64, true, true],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'owner' => ['owner', 'string', 255, false, null],
            'name' => ['name', 'string', 255, false, null],
            'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
        ]];
    }

    /**
     * @param class-string                                                    $class
     * @param Closure(): object                                               $make    builds one, which runs the lines of the mapping
     * @param array<string, array{string, string, int|null, bool, bool|null}> $columns
     */
    #[DataProvider('tables')]
    public function testEachColumnIsMappedAsTheSchemaExpects(string $class, Closure $make, string $table, array $columns): void
    {
        self::assertInstanceOf($class, $make());

        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        $mapped = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $mapped[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame($table, $metadata->getTableName());
        self::assertSame($columns, $mapped);
    }

    private static function order(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', self::cad(1_000), 'goods'), 1, self::cad(1_000));
        $order->place(new DateTimeImmutable());

        return $order;
    }

    private static function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }
}
