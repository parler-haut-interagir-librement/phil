<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SellableReference::class)]
#[CoversClass(Sellable::class)]
#[CoversClass(PricingContext::class)]
#[CoversClass(Adjustment::class)]
final class SellableTest extends TestCase
{
    public function testAReferenceIsATypeAndAnIdentifier(): void
    {
        $reference = SellableReference::of('gift_card', 'GC-2026/01');

        self::assertSame('gift_card', $reference->type);
        self::assertSame('GC-2026/01', $reference->id);
        self::assertSame('gift_card:GC-2026/01', $reference->value());
        self::assertTrue($reference->equals(SellableReference::of('gift_card', 'GC-2026/01')));
        self::assertFalse($reference->equals(SellableReference::of('gift_card', 'GC-2026/02')));
        self::assertFalse($reference->equals(SellableReference::of('product', 'GC-2026/01')));
    }

    public function testTheBoundsOfAReferenceAreAccepted(): void
    {
        self::assertSame('a', SellableReference::of('a', '1')->type);
        self::assertSame('a'.str_repeat('b', 31), SellableReference::of('a'.str_repeat('b', 31), '1')->type);
        self::assertSame(str_repeat('x', 128), SellableReference::of('p', str_repeat('x', 128))->id);
        self::assertSame('!~', SellableReference::of('p', '!~')->id);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidReferences(): iterable
    {
        yield 'empty type' => ['', '1', '"" is not a sellable type: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'uppercase type' => ['Product', '1', '"Product" is not a sellable type'];
        yield 'type starting with a digit' => ['1product', '1', '"1product" is not a sellable type'];
        yield 'type too long' => [str_repeat('a', 33), '1', 'is not a sellable type'];
        yield 'type with a trailing newline' => ["product\n", '1', 'is not a sellable type'];
        yield 'empty identifier' => ['product', '', '"" is not a sellable identifier: 1 to 128 visible ASCII characters.'];
        yield 'identifier with a space' => ['product', 'a b', '"a b" is not a sellable identifier'];
        yield 'identifier too long' => ['product', str_repeat('x', 129), 'is not a sellable identifier'];
        yield 'identifier with a trailing newline' => ['product', "1\n", 'is not a sellable identifier'];
        yield 'identifier beyond ASCII' => ['product', 'é', 'is not a sellable identifier'];
    }

    #[DataProvider('invalidReferences')]
    public function testAnInvalidReferenceIsRefused(string $type, string $id, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        SellableReference::of($type, $id);
    }

    public function testASellableHoldsWhatTheCoreNeeds(): void
    {
        $reference = SellableReference::of('product', '42');
        $sellable = Sellable::of($reference, 'Dice set', Money::of(1_999, Currency::of('CAD')), 'goods');

        self::assertSame($reference, $sellable->reference);
        self::assertSame('Dice set', $sellable->description);
        self::assertSame(1_999, $sellable->unitPrice->minorUnits);
        self::assertSame('goods', $sellable->taxCategory);
        self::assertTrue(Sellable::of($reference, 'Free sample', Money::zero(Currency::of('CAD')), 'goods')->unitPrice->isZero());
    }

    /**
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function invalidSellables(): iterable
    {
        yield 'no description' => ['', 100, 'goods', 'The sellable product:42 has no description.'];
        yield 'blank description' => [' ', 100, 'goods', 'The sellable product:42 has no description.'];
        yield 'negative price' => ['Dice', -1, 'goods', 'The sellable product:42 has a negative unit price; a discount is an adjustment.'];
        yield 'no tax category' => ['Dice', 100, '', 'The sellable product:42 has no tax category.'];
    }

    #[DataProvider('invalidSellables')]
    public function testAnInvalidSellableIsRefused(string $description, int $price, string $category, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Sellable::of(SellableReference::of('product', '42'), $description, Money::of($price, Currency::of('CAD')), $category);
    }

    public function testAPricingContextCarriesWhatAPriceMayDependOn(): void
    {
        $at = new DateTimeImmutable('2026-11-03T12:00:00Z');
        $context = PricingContext::of(3, $at, 'wholesale', 'web');

        self::assertSame(3, $context->quantity);
        self::assertSame($at, $context->at);
        self::assertSame('wholesale', $context->customerGroup);
        self::assertSame('web', $context->channel);

        $plain = PricingContext::of(1, $at);
        self::assertNull($plain->customerGroup);
        self::assertNull($plain->channel);
    }

    public function testAPriceIsAskedForAtLeastOneUnit(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A price is asked for at least one unit; 0 is not.');

        PricingContext::of(0, new DateTimeImmutable());
    }

    public function testAnEmptyCustomerGroupIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A customer group or a channel, when given, has a name.');

        PricingContext::of(1, new DateTimeImmutable(), '');
    }

    public function testAnEmptyChannelIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        PricingContext::of(1, new DateTimeImmutable(), null, '');
    }

    public function testAnAdjustmentIsOnTheOrderOrOnALine(): void
    {
        $line = SellableReference::of('product', '42');
        $onLine = Adjustment::of(AdjustmentType::Promotion, 'Two for one', Money::of(-500, Currency::of('CAD')), $line);
        $onOrder = Adjustment::of(AdjustmentType::Tax, 'VAT', Money::of(200, Currency::of('EUR')), neutral: true);

        self::assertSame(AdjustmentType::Promotion, $onLine->type);
        self::assertSame('Two for one', $onLine->label);
        self::assertSame(-500, $onLine->amount->minorUnits);
        self::assertSame($line, $onLine->line);
        self::assertFalse($onLine->neutral);
        self::assertNull($onOrder->line);
        self::assertTrue($onOrder->neutral);
        self::assertNull($onLine->origin);
    }

    public function testAnAdjustmentKeepsWhatGaveIt(): void
    {
        $longest = str_repeat('x', 255);

        self::assertSame('promotion:black-friday:SAVE-10', Adjustment::of(AdjustmentType::Promotion, 'Sale', Money::of(-500, Currency::of('CAD')), origin: 'promotion:black-friday:SAVE-10')->origin);
        self::assertSame($longest, Adjustment::of(AdjustmentType::Fee, 'Fee', Money::of(1, Currency::of('CAD')), origin: $longest)->origin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidOrigins(): iterable
    {
        yield 'empty' => [''];
        yield 'with a space' => ['promotion: sale'];
        yield 'too long' => [str_repeat('x', 256)];
        yield 'with a trailing newline' => ["promotion:sale\n"];
    }

    #[DataProvider('invalidOrigins')]
    public function testAnInvalidOriginIsRefused(string $origin): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not the origin of an adjustment: 1 to 255 visible ASCII characters.', $origin));

        Adjustment::of(AdjustmentType::Promotion, 'Sale', Money::of(-500, Currency::of('CAD')), origin: $origin);
    }

    public function testAnAdjustmentHasALabel(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A shipping adjustment has a label, which the receipt shows.');

        Adjustment::of(AdjustmentType::Shipping, ' ', Money::of(1_000, Currency::of('CAD')));
    }
}
