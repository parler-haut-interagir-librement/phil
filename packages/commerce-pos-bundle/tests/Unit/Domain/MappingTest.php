<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the register.
 */
#[CoversClass(Register::class)]
#[CoversClass(CashSession::class)]
#[CoversClass(CashMovement::class)]
#[CoversClass(RegisterSale::class)]
#[CoversClass(SessionPayment::class)]
#[CoversClass(HeldSale::class)]
#[CoversClass(Layaway::class)]
#[CoversClass(ImportedSale::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminalPayment::class)]
#[CoversClass(SalePreset::class)]
#[CoversClass(CashierPin::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        self::assertSame(['phil_commerce_pos_register', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 32, false, null],
            'name' => ['name', 'string', 255, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'cashIncrement' => ['cash_increment', 'smallint', null, false, null],
            'location' => ['location', 'string', 64, true, null],
            'channel' => ['channel', 'string', 64, true, null],
            'role' => ['role', 'string', 8, false, null],
        ]], $this->table(Register::class));
        self::assertSame(['phil_commerce_pos_session', ['id'], [
            'expectedMinorUnits' => ['expected', 'bigint', null, false, null],
            'state' => ['state', 'string', 8, false, null],
            'countedMinorUnits' => ['counted', 'bigint', null, true, null],
            'closedBy' => ['closed_by', 'string', 255, true, null],
            'closedAt' => ['closed_at', 'datetime_utc_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'register' => ['register', 'string', 32, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'openedBy' => ['opened_by', 'string', 255, false, null],
            'openedAt' => ['opened_at', 'datetime_utc_immutable', null, false, null],
            'taxIncluded' => ['tax_included', 'boolean', null, false, null],
        ]], $this->table(CashSession::class));
        self::assertSame(['phil_commerce_pos_cash_movement', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'session' => ['session', 'string', 36, false, true],
            'kind' => ['kind', 'string', 16, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'roundingMinorUnits' => ['rounding', 'integer', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'by' => ['by_whom', 'string', 255, false, null],
            'occurredAt' => ['occurred_at', 'datetime_utc_immutable', null, false, null],
            'payment' => ['payment', 'string', 36, true, true],
            'note' => ['note', 'string', 255, true, null],
        ]], $this->table(CashMovement::class));
        self::assertSame(['phil_commerce_pos_sale', ['orderNumber'], [
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'session' => ['session', 'string', 36, false, true],
            'register' => ['register', 'string', 32, false, null],
            'by' => ['by_whom', 'string', 255, false, null],
            'rungAt' => ['rung_at', 'datetime_utc_immutable', null, false, null],
            'tipMinorUnits' => ['tip', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'preset' => ['preset', 'string', 32, true, null],
        ]], $this->table(RegisterSale::class));
        self::assertSame(['phil_commerce_pos_payment', ['payment'], [
            'payment' => ['payment', 'string', 36, false, true],
            'session' => ['session', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'method' => ['method', 'string', 32, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'approval' => ['approval', 'string', 255, true, null],
            'takenAt' => ['taken_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(SessionPayment::class));
        self::assertSame(['phil_commerce_pos_held_sale', ['orderNumber'], [
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'register' => ['register', 'string', 32, false, null],
            'label' => ['label', 'string', 64, false, null],
            'heldBy' => ['held_by', 'string', 255, false, null],
            'heldAt' => ['held_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(HeldSale::class));
        self::assertSame(['phil_commerce_pos_layaway', ['orderNumber'], [
            'state' => ['state', 'string', 16, false, null],
            'endedAt' => ['ended_at', 'datetime_utc_immutable', null, true, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'customer' => ['customer', 'string', 255, false, null],
            'openedAt' => ['opened_at', 'datetime_utc_immutable', null, false, null],
            'dueAt' => ['due_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(Layaway::class));
        self::assertSame(['phil_commerce_pos_imported_sale', ['id'], [
            'id' => ['id', 'string', 64, false, null],
            'session' => ['session', 'string', 36, false, true],
            'discrepancyMinorUnits' => ['discrepancy', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'rungAt' => ['rung_at', 'datetime_utc_immutable', null, false, null],
            'importedAt' => ['imported_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(ImportedSale::class));
        self::assertSame(['phil_commerce_pos_terminal', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 32, false, null],
            'register' => ['register', 'string', 32, false, null],
            'adapter' => ['adapter', 'string', 32, false, null],
            'device' => ['device', 'string', 255, false, null],
            'label' => ['label', 'string', 64, false, null],
            'pinEntry' => ['pin_entry', 'boolean', null, false, null],
        ]], $this->table(Terminal::class));
        self::assertSame(['phil_commerce_pos_terminal_payment', ['payment'], [
            'counted' => ['counted', 'boolean', null, false, null],
            'payment' => ['payment', 'string', 36, false, true],
            'terminal' => ['terminal', 'string', 32, false, null],
            'session' => ['session', 'string', 36, false, true],
            'processorReference' => ['processor_reference', 'string', 255, false, null],
            'presentedAt' => ['presented_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(TerminalPayment::class));
        self::assertSame(['phil_commerce_pos_sale_preset', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 32, false, null],
            'label' => ['label', 'string', 64, false, null],
            'channel' => ['channel', 'string', 64, true, null],
            'customerGroup' => ['customer_group', 'string', 64, true, null],
            'identifiesCustomer' => ['identifies_customer', 'boolean', null, false, null],
        ]], $this->table(SalePreset::class));
        self::assertSame(['phil_commerce_pos_cashier_pin', ['user'], [
            'user' => ['user_identifier', 'string', 255, false, null],
            'hash' => ['hash', 'string', 255, false, null],
            'setAt' => ['set_at', 'datetime_utc_immutable', null, false, null],
        ]], $this->table(CashierPin::class));
    }

    public function testTheIndexes(): void
    {
        self::assertSame([['columns' => ['register', 'state']]], array_values($this->metadata(CashSession::class)->table['indexes'] ?? []));
        self::assertTrue($this->metadata(CashSession::class)->isVersioned);
        self::assertSame(['default' => false], $this->metadata(CashSession::class)->fieldMappings['taxIncluded']->options);

        foreach ([CashMovement::class, RegisterSale::class, SessionPayment::class] as $class) {
            self::assertSame([['columns' => ['session']]], array_values($this->metadata($class)->table['indexes'] ?? []), $class);
        }

        self::assertSame([['columns' => ['register']]], array_values($this->metadata(HeldSale::class)->table['indexes'] ?? []));
        self::assertSame([['columns' => ['state', 'due_at']]], array_values($this->metadata(Layaway::class)->table['indexes'] ?? []));
        self::assertSame([['columns' => ['register']]], array_values($this->metadata(Terminal::class)->table['indexes'] ?? []));
        self::assertSame(['default' => 'mobile'], $this->metadata(Register::class)->fieldMappings['role']->options);
    }

    public function testTheLongestValuesFit(): void
    {
        $at = new DateTimeImmutable();
        $cad = Currency::of('CAD');
        $register = Register::of(str_repeat('r', 32), str_repeat('n', 255), $cad, 5, str_repeat('l', 64), str_repeat('c', 64));
        [$session, $float] = CashSession::open($register, str_repeat('b', 255), Money::of(0, $cad), $at);
        $movement = $session->move(CashMovementKind::Sale, Money::of(1, $cad), Money::of(0, $cad), str_repeat('b', 255), $at, str_repeat('p', 36), str_repeat('n', 255));
        $session->close(Money::of(0, $cad), str_repeat('c', 255), $at);
        $sale = RegisterSale::of(str_repeat('o', 64), $session, str_repeat('b', 255), $at);
        $payment = SessionPayment::of(str_repeat('p', 36), $session, str_repeat('o', 64), str_repeat('m', 32), Money::of(1, $cad), str_repeat('a', 255), $at);
        $held = HeldSale::of(str_repeat('o', 64), $register, str_repeat('l', 64), str_repeat('h', 255), $at);
        $layaway = Layaway::of(str_repeat('o', 64), str_repeat('c', 255), $at, $at->modify('+1 day'));
        $imported = ImportedSale::of(str_repeat('i', 64), $session, Money::of(0, $cad), $at, $at);
        $terminal = Terminal::of(str_repeat('t', 32), $register, str_repeat('a', 32), str_repeat('d', 255), str_repeat('l', 64), false);
        $presented = TerminalPayment::of(str_repeat('p', 36), $terminal, $session, str_repeat('r', 255), $at);
        $preset = SalePreset::of(str_repeat('p', 32), str_repeat('l', 64), str_repeat('c', 64), str_repeat('g', 64));
        $pin = CashierPin::of(str_repeat('u', 255), str_repeat('h', 255), $at);
        $presetSale = RegisterSale::of('B-1', $session, 'ada', $at, null, $preset->code);

        $lengths = [
            [Register::class, 'code', $register->code], [Register::class, 'name', $register->name()], [Register::class, 'location', (string) $register->location()], [Register::class, 'channel', (string) $register->channel()],
            [CashSession::class, 'id', $session->id], [CashSession::class, 'register', $session->register], [CashSession::class, 'openedBy', $session->openedBy], [CashSession::class, 'closedBy', (string) $session->closedBy()],
            [CashMovement::class, 'id', $movement->id], [CashMovement::class, 'session', $movement->session], [CashMovement::class, 'by', $movement->by], [CashMovement::class, 'payment', (string) $movement->payment], [CashMovement::class, 'note', (string) $movement->note],
            [RegisterSale::class, 'orderNumber', $sale->orderNumber], [RegisterSale::class, 'session', $sale->session], [RegisterSale::class, 'register', $sale->register], [RegisterSale::class, 'by', $sale->by],
            [SessionPayment::class, 'payment', $payment->payment], [SessionPayment::class, 'session', $payment->session], [SessionPayment::class, 'orderNumber', $payment->orderNumber], [SessionPayment::class, 'method', $payment->method], [SessionPayment::class, 'approval', (string) $payment->approval],
            [HeldSale::class, 'orderNumber', $held->orderNumber], [HeldSale::class, 'register', $held->register], [HeldSale::class, 'label', $held->label], [HeldSale::class, 'heldBy', $held->heldBy],
            [Layaway::class, 'orderNumber', $layaway->orderNumber], [Layaway::class, 'customer', $layaway->customer],
            [ImportedSale::class, 'id', $imported->id], [ImportedSale::class, 'session', $imported->session],
            [Terminal::class, 'code', $terminal->code], [Terminal::class, 'register', $terminal->register], [Terminal::class, 'adapter', $terminal->adapter], [Terminal::class, 'device', $terminal->device], [Terminal::class, 'label', $terminal->label()],
            [TerminalPayment::class, 'payment', $presented->payment], [TerminalPayment::class, 'terminal', $presented->terminal], [TerminalPayment::class, 'session', $presented->session], [TerminalPayment::class, 'processorReference', $presented->processorReference],
            [SalePreset::class, 'code', $preset->code], [SalePreset::class, 'label', $preset->label()], [SalePreset::class, 'channel', (string) $preset->channel()], [SalePreset::class, 'customerGroup', (string) $preset->customerGroup()],
            [CashierPin::class, 'user', $pin->user], [CashierPin::class, 'hash', $pin->hash()],
            [RegisterSale::class, 'preset', (string) $presetSale->preset],
        ];

        foreach ($lengths as [$class, $field, $value]) {
            self::assertSame($this->metadata($class)->fieldMappings[$field]->length, \strlen($value), $class.'::'.$field);
        }

        self::assertSame(36, \strlen($float->id));
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
