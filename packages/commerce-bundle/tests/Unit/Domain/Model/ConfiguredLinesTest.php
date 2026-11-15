<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
#[CoversClass(OrderLine::class)]
#[CoversClass(Sellable::class)]
#[CoversClass(OrderLocked::class)]
final class ConfiguredLinesTest extends TestCase
{
    public function testTwoConfigurationsOfOneSellableAreTwoLines(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $sellable = $this->shirt();

        $bob = $order->add($sellable, 1, $this->cad(2_500), $this->text('Bob'));
        $alice = $order->add($sellable, 2, $this->cad(2_500), $this->text('Alice'));
        $again = $order->add($sellable, 3, $this->cad(2_400), $this->text('Bob'));

        self::assertCount(2, $order->lines());
        self::assertFalse($bob->reference->equals($alice->reference));
        self::assertTrue($again->reference->equals($bob->reference));
        self::assertSame(4, $again->quantity);
        self::assertSame(2_400, $again->unitPrice->minorUnits);
        self::assertSame(4, $order->line($bob->reference)?->quantity);
        self::assertSame(2, $order->line($alice->reference)?->quantity);
        $text = $order->line($bob->reference)->configuration->value('text');
        self::assertNotNull($text);
        self::assertSame(['Bob', 'Name'], [$text->value, $text->label]);
        self::assertNull($order->line(SellableReference::of('product', 'shirt')));
        self::assertSame('shirt', $bob->reference->id);
        self::assertSame($this->text('Bob')->fingerprint(), $bob->reference->configuration);

        $order->replaceAdjustments(AdjustmentType::Option, [Adjustment::of(AdjustmentType::Option, 'Name', $this->cad(500), $bob->reference)]);
        $order->remove($alice->reference);
        self::assertCount(1, $order->adjustments(AdjustmentType::Option));
        $order->remove($bob->reference);
        self::assertSame([], $order->adjustments());
    }

    public function testAnOrderRefusesAConfigurationItsSellableRefuses(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The option text is required.');

        $order->add($this->shirt(), 1, $this->cad(2_500));
    }

    public function testASellableWithoutOptionsTakesNoConfiguration(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $plain = Sellable::of(SellableReference::of('product', 'mug'), 'Mug', $this->cad(1_000), 'goods');

        $line = $order->add($plain, 1, $this->cad(1_000));
        self::assertTrue($line->configuration->isEmpty());
        self::assertNull($line->reference->configuration);
        self::assertTrue($plain->options->isEmpty());

        $this->expectException(InvalidConfiguration::class);
        $order->add($plain, 1, $this->cad(1_000), $this->text('Bob'));
    }

    public function testASellableIsNamedWithoutALinesConfiguration(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is named without the configuration of a line.');

        Sellable::of(SellableReference::of('product', 'mug', '0123456789abcdef'), 'Mug', $this->cad(1_000), 'goods');
    }

    public function testASellableTakesItsOptions(): void
    {
        $options = OptionSet::of(OptionDefinition::draw('loot', 'Loot', 'tables'));
        $plain = Sellable::of(SellableReference::of('product', 'mug'), 'Mug', $this->cad(1_000), 'goods');
        $with = $plain->withOptions($options);

        self::assertSame($options, $with->options);
        self::assertSame(['mug', 'Mug', 1_000, 'goods'], [$with->reference->id, $with->description, $with->unitPrice->minorUnits, $with->taxCategory]);
        self::assertSame($options, Sellable::of(SellableReference::of('product', 'mug'), 'Mug', $this->cad(1_000), 'goods', $options)->options);
    }

    public function testDrawsAreRecordedOnAPlacedOrderOnly(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $loot = Sellable::of(SellableReference::of('product', 'loot'), 'Loot', $this->cad(3_000), 'goods', OptionSet::of(OptionDefinition::draw('dice', 'Dice', 'tables')));
        $line = $order->add($loot, 2, $this->cad(3_000));

        self::assertSame(['dice'], $line->configuration->pending());

        try {
            $order->recordDraws($line->reference, 'dice', ['1', '2']);
            self::fail('A cart took draws.');
        } catch (OrderLocked $orderLocked) {
            self::assertSame('The order A-1 is cart: its draws are made once it is placed and paid.', $orderLocked->getMessage());
        }

        $order->place(new DateTimeImmutable('2026-11-07'));
        $order->recordDraws($line->reference, 'dice', ['352614', '111111']);

        $drawn = $order->line($line->reference);
        self::assertNotNull($drawn);
        self::assertSame(['352614', '111111'], $drawn->configuration->value('dice')?->draws);
        self::assertSame([], $drawn->configuration->pending());
        self::assertSame(2, $drawn->quantity);
        self::assertSame('Loot', $drawn->description);
        self::assertSame(3_000, $drawn->unitPrice->minorUnits);
        self::assertSame('goods', $drawn->taxCategory);
        self::assertSame(['product', $line->reference->value()], [$drawn->reference->type, $drawn->reference->value()]);

        $order->reopen();
        self::assertSame(2_000, $drawn->withUnitPrice($this->cad(2_000))->unitPrice->minorUnits);
        self::assertSame(['352614', '111111'], $drawn->withUnitPrice($this->cad(2_000))->configuration->value('dice')?->draws);

        try {
            $drawn->withQuantity(3);
            self::fail('A drawn line changed its quantity.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString('was drawn for 2 units; its quantity no longer changes.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('has no option dice still to draw.');
        $drawn->withDraws('dice', ['1', '2']);
    }

    public function testALineTakesOneDrawPerUnit(): void
    {
        $loot = Sellable::of(SellableReference::of('product', 'loot'), 'Loot', $this->cad(3_000), 'goods', OptionSet::of(OptionDefinition::draw('dice', 'Dice', 'tables')));
        $line = OrderLine::of($loot, 2, $this->cad(3_000));

        self::assertSame(3, $line->withQuantity(3)->quantity);
        self::assertSame(['dice'], $line->withQuantity(3)->configuration->pending());
        self::assertSame(['dice'], $line->withUnitPrice($this->cad(1))->configuration->pending());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('holds 2 units: its option dice takes 2 draws, not 1.');

        $line->withDraws('dice', ['1']);
    }

    public function testAStoredLineKeepsItsConfigurationUnchecked(): void
    {
        $plain = Sellable::of(SellableReference::of('product', 'mug'), 'Mug', $this->cad(1_000), 'goods');
        $line = OrderLine::stored($plain, 1, $this->cad(1_000), $this->text('Bob'));

        self::assertSame('Bob', $line->configuration->value('text')?->value);
        self::assertSame($this->text('Bob')->fingerprint(), $line->reference->configuration);
    }

    private function shirt(): Sellable
    {
        $options = OptionSet::of(
            OptionDefinition::text('text', 'Name', TextConstraints::of(10), true),
            OptionDefinition::choice('frame', 'Frame', [OptionChoice::of('plain', 'Plain')]),
        );

        return Sellable::of(SellableReference::of('product', 'shirt'), 'Shirt', $this->cad(2_500), 'goods', $options);
    }

    private function text(string $text): LineConfiguration
    {
        return LineConfiguration::of(['text' => OptionValue::chosen($text)]);
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
