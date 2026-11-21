<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for stored value.
 */
#[CoversClass(Instrument::class)]
#[CoversClass(Movement::class)]
final class MappingTest extends TestCase
{
    public function testTheInstruments(): void
    {
        self::assertSame(['phil_commerce_stored_value_instrument', ['id'], [
            'balanceMinorUnits' => ['balance', 'bigint', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'kind' => ['kind', 'string', 16, false, null],
            'codeHash' => ['code_hash', 'string', 64, true, true],
            'lastFour' => ['last_four', 'string', 4, true, true],
            'holder' => ['holder', 'string', 255, true, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'openedAt' => ['opened_at', 'datetime_immutable', null, false, null],
            'expiresAt' => ['expires_at', 'datetime_immutable', null, true, null],
            'origin' => ['origin', 'string', 16, true, null],
            'specificService' => ['specific_service', 'boolean', null, false, null],
        ]], $this->table(Instrument::class));
        self::assertSame(GiftCardOrigin::class, $this->metadata(Instrument::class)->fieldMappings['origin']->enumType);
        self::assertFalse($this->metadata(Instrument::class)->fieldMappings['specificService']->options['default'] ?? null);
        self::assertSame([['columns' => ['code_hash']], ['columns' => ['holder', 'currency']]], array_values($this->metadata(Instrument::class)->table['uniqueConstraints'] ?? []));
        self::assertTrue($this->metadata(Instrument::class)->isVersioned);

        $credit = Instrument::storeCredit(str_repeat('h', 255), Currency::of('CAD'), new DateTimeImmutable());
        self::assertSame($this->metadata(Instrument::class)->fieldMappings['holder']->length, \strlen((string) $credit->holder));
        self::assertSame($this->metadata(Instrument::class)->fieldMappings['id']->length, \strlen($credit->id));
    }

    public function testTheMovements(): void
    {
        self::assertSame(['phil_commerce_stored_value_movement', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'instrument' => ['instrument', 'string', 36, false, true],
            'kind' => ['kind', 'string', 16, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'balanceAfterMinorUnits' => ['balance_after', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'occurredAt' => ['occurred_at', 'datetime_immutable', null, false, null],
            'orderNumber' => ['order_number', 'string', 64, true, null],
            'payment' => ['payment', 'string', 36, true, true],
            'note' => ['note', 'string', 255, true, null],
            'issuedUnit' => ['issued_unit', 'integer', null, true, null],
            'idempotencyKey' => ['idempotency_key', 'string', 128, true, null],
        ]], $this->table(Movement::class));
        self::assertSame([['columns' => ['instrument']], ['columns' => ['payment']], ['columns' => ['order_number']]], array_values($this->metadata(Movement::class)->table['indexes'] ?? []));
        self::assertSame([
            'phil_commerce_stored_value_movement_issued_unit' => ['columns' => ['order_number', 'kind', 'issued_unit']],
            'phil_commerce_stored_value_movement_idempotency_key' => ['columns' => ['instrument', 'idempotency_key']],
        ], $this->metadata(Movement::class)->table['uniqueConstraints'] ?? []);
        self::assertSame(Movement::IDEMPOTENCY_KEY_LENGTH, $this->metadata(Movement::class)->fieldMappings['idempotencyKey']->length);

        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), new DateTimeImmutable());
        $movement = $credit->credit(MovementKind::Granted, Money::of(1, Currency::of('CAD')), new DateTimeImmutable(), str_repeat('o', 64), str_repeat('p', 36), str_repeat('n', 255));
        self::assertSame($this->metadata(Movement::class)->fieldMappings['orderNumber']->length, \strlen((string) $movement->orderNumber));
        self::assertSame($this->metadata(Movement::class)->fieldMappings['payment']->length, \strlen((string) $movement->payment));
        self::assertSame($this->metadata(Movement::class)->fieldMappings['note']->length, \strlen((string) $movement->note));
        self::assertSame($this->metadata(Movement::class)->fieldMappings['id']->length, \strlen($movement->id));
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<int, string>, array<string, array{string, string, ?int, ?bool, mixed}>}
     */
    private function table(string $class): array
    {
        $metadata = $this->metadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
