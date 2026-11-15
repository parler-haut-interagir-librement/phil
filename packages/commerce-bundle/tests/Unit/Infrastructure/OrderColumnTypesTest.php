<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\AdjustmentsType;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\OrderLinesType;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\UnknownCurrency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderLinesType::class)]
#[CoversClass(AdjustmentsType::class)]
final class OrderColumnTypesTest extends TestCase
{
    public function testLinesAreStoredAsAJsonListAndReadBackKeyed(): void
    {
        $type = new OrderLinesType();
        $platform = new SQLitePlatform();
        $price = Money::of(1_999, Currency::of('CAD'));
        $line = OrderLine::of(Sellable::of(SellableReference::of('product', 'd6/rouge'), 'Dé à 6 faces', $price, 'goods'), 3, $price);

        $json = $type->convertToDatabaseValue(['product:d6/rouge' => $line], $platform);
        $other = OrderLine::of(Sellable::of(SellableReference::of('product', 'd8'), 'D8', $price, 'goods'), 1, $price);
        $two = $type->convertToPHPValue($type->convertToDatabaseValue(['product:d6/rouge' => $line, 'product:d8' => $other], $platform), $platform);
        self::assertSame(['product:d6/rouge', 'product:d8'], array_keys($two));

        self::assertSame('[{"type":"product","id":"d6/rouge","description":"Dé à 6 faces","quantity":3,"unit_price":1999,"currency":"CAD","tax_category":"goods"}]', $json);

        $lines = $type->convertToPHPValue($json, $platform);
        self::assertSame(['product:d6/rouge'], array_keys($lines));
        self::assertSame(3, $lines['product:d6/rouge']->quantity);
        self::assertSame('Dé à 6 faces', $lines['product:d6/rouge']->description);
        self::assertTrue($lines['product:d6/rouge']->unitPrice->equals($price));
        self::assertSame('goods', $lines['product:d6/rouge']->taxCategory);
        self::assertSame([], $type->convertToPHPValue('[]', $platform));
        self::assertSame('[]', $type->convertToDatabaseValue([], $platform));
    }

    public function testAdjustmentsAreStoredAsAJsonList(): void
    {
        $type = new AdjustmentsType();
        $platform = new SQLitePlatform();
        $line = SellableReference::of('product', 'dice');
        $adjustments = [
            Adjustment::of(AdjustmentType::Tax, 'TVQ/Québec', Money::of(199, Currency::of('CAD')), $line),
            Adjustment::of(AdjustmentType::Fee, 'Frais', Money::of(-50, Currency::of('CAD')), neutral: true, origin: 'fee:service'),
        ];

        $json = $type->convertToDatabaseValue($adjustments, $platform);

        self::assertSame('[{"type":"tax","label":"TVQ/Québec","amount":199,"currency":"CAD","line":{"type":"product","id":"dice"},"neutral":false,"origin":null},{"type":"fee","label":"Frais","amount":-50,"currency":"CAD","line":null,"neutral":true,"origin":"fee:service"}]', $json);

        $read = $type->convertToPHPValue($json, $platform);
        self::assertCount(2, $read);
        self::assertSame(AdjustmentType::Tax, $read[0]->type);
        self::assertSame('TVQ/Québec', $read[0]->label);
        self::assertSame(199, $read[0]->amount->minorUnits);
        self::assertSame('product:dice', $read[0]->line?->value());
        self::assertFalse($read[0]->neutral);
        self::assertNull($read[1]->line);
        self::assertTrue($read[1]->neutral);
        self::assertSame(-50, $read[1]->amount->minorUnits);
        self::assertNull($read[0]->origin);
        self::assertSame('fee:service', $read[1]->origin);
    }

    /**
     * A row written without an origin reads as an adjustment without one.
     */
    public function testAnAdjustmentWithoutAnOriginKeyHasNone(): void
    {
        $read = new AdjustmentsType()->convertToPHPValue('[{"type":"tax","label":"GST","amount":5,"currency":"CAD","line":null,"neutral":false}]', new SQLitePlatform());

        self::assertNull($read[0]->origin);
    }

    public function testBothAreJsonColumns(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame('JSON', new OrderLinesType()->getSQLDeclaration([], $platform));
        self::assertSame('JSON', new AdjustmentsType()->getSQLDeclaration([], $platform));
    }

    public function testOnlyAListOfLinesIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: '.OrderLine::class);

        new OrderLinesType()->convertToDatabaseValue(['product:1' => 'a line'], new SQLitePlatform());
    }

    public function testOnlyAnArrayOfLinesIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: array');

        new OrderLinesType()->convertToDatabaseValue(null, new SQLitePlatform());
    }

    public function testOnlyAListOfAdjustmentsIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: '.Adjustment::class);

        new AdjustmentsType()->convertToDatabaseValue(['an adjustment'], new SQLitePlatform());
    }

    public function testOnlyAnArrayOfAdjustmentsIsStored(): void
    {
        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageIsOrContains('Expected one of the following types: array');

        new AdjustmentsType()->convertToDatabaseValue('[]', new SQLitePlatform());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unreadableLines(): iterable
    {
        $valid = ['type' => 'product', 'id' => '1', 'description' => 'D', 'quantity' => 1, 'unit_price' => 100, 'currency' => 'CAD', 'tax_category' => 'goods'];

        yield 'not a string' => [42];
        yield 'not JSON' => ['{'];
        yield 'an object' => ['{"a":1}'];
        yield 'a scalar' => ['"lines"'];
        yield 'an object of valid lines' => [json_encode(['first' => $valid])];
        yield 'a line that is not an object' => ['[1]'];

        foreach (array_keys($valid) as $key) {
            $missing = $valid;
            unset($missing[$key]);

            yield 'no '.$key => [json_encode([$missing])];
            yield $key.' of the wrong type' => [json_encode([[...$valid, $key => true]])];
        }

        yield 'a refused quantity' => [json_encode([[...$valid, 'quantity' => 0]])];
        yield 'an unknown currency' => [json_encode([[...$valid, 'currency' => 'XYZ']])];

        $option = ['value' => 'Bob', 'label' => 'Name', 'display' => null, 'drawer' => null, 'draws' => []];
        $configured = static fn (mixed $configuration): string => (string) json_encode([[...$valid, 'configuration' => $configuration]]);

        yield 'a configuration that is not an object' => [$configured('text')];
        yield 'a configuration of null' => [$configured(null)];
        yield 'a configuration without a version' => [$configured(['options' => ['name' => $option]])];
        yield 'a configuration of another version' => [$configured(['version' => 2, 'options' => ['name' => $option]])];
        yield 'a configuration of a version as text' => [$configured(['version' => '1', 'options' => ['name' => $option]])];
        yield 'a configuration without options' => [$configured(['version' => 1])];
        yield 'a configuration of no option' => [$configured(['version' => 1, 'options' => []])];
        yield 'options that are not an object' => [$configured(['version' => 1, 'options' => 'name'])];
        yield 'an option that is not an object' => [$configured(['version' => 1, 'options' => ['name' => 'Bob']])];
        yield 'a refused option code' => [$configured(['version' => 1, 'options' => ['Name' => $option]])];
        yield 'a numeric option code' => [$configured(['version' => 1, 'options' => ['7' => $option]])];

        foreach (array_keys($option) as $key) {
            $missing = $option;
            unset($missing[$key]);

            yield 'an option without '.$key => [$configured(['version' => 1, 'options' => ['name' => $missing]])];
            yield 'an option with '.$key.' of the wrong type' => [$configured(['version' => 1, 'options' => ['name' => [...$option, $key => 1]]])];
        }

        yield 'an option without a label' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'label' => null]]])];
        yield 'draws that are not a list' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'value' => null, 'drawer' => 'd', 'draws' => ['a' => '1']]]])];
        yield 'a draw that is not a string' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'value' => null, 'drawer' => 'd', 'draws' => ['1', 2]]]])];
        yield 'draws on a chosen value' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'draws' => ['1']]]])];
        yield 'a value to draw without a drawer' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'value' => null]]])];
        yield 'a blank value' => [$configured(['version' => 1, 'options' => ['name' => [...$option, 'value' => ' ']]])];
    }

    public function testAConfiguredLineIsStoredWithItsVersionedConfiguration(): void
    {
        $type = new OrderLinesType();
        $platform = new SQLitePlatform();
        $price = Money::of(2_500, Currency::of('CAD'));
        $configuration = LineConfiguration::of([
            'name' => OptionValue::chosen('Éloï')->described('Name'),
            'stone' => OptionValue::chosen('marble')->described('Stone', 'Marble'),
            'loot' => OptionValue::toDraw()->described('Loot', null, 'dice')->withDraws(['352614']),
            'verse' => OptionValue::toDraw()->described('Verse', null, 'verses'),
        ]);
        $line = OrderLine::stored(Sellable::of(SellableReference::of('product', 'shirt'), 'Shirt', $price, 'goods'), 1, $price, $configuration);

        $json = $type->convertToDatabaseValue([$line->reference->value() => $line], $platform);

        self::assertSame('[{"type":"product","id":"shirt","description":"Shirt","quantity":1,"unit_price":2500,"currency":"CAD","tax_category":"goods","configuration":{"version":1,"options":{"loot":{"value":null,"label":"Loot","display":null,"drawer":"dice","draws":["352614"]},"name":{"value":"Éloï","label":"Name","display":null,"drawer":null,"draws":[]},"stone":{"value":"marble","label":"Stone","display":"Marble","drawer":null,"draws":[]},"verse":{"value":null,"label":"Verse","display":null,"drawer":"verses","draws":[]}}}}]', $json);

        $read = $type->convertToPHPValue($json, $platform);
        self::assertSame([$line->reference->value()], array_keys($read));
        $back = $read[$line->reference->value()];
        self::assertEquals($configuration, $back->configuration);
        self::assertTrue($back->reference->equals($line->reference));
        self::assertSame(['verse'], $back->configuration->pending());
        self::assertSame('Marble', $back->configuration->value('stone')?->shown());
    }

    public function testAnAdjustmentOnAConfiguredLineKeepsItsFingerprint(): void
    {
        $type = new AdjustmentsType();
        $platform = new SQLitePlatform();
        $line = SellableReference::of('product', 'shirt', '0123456789abcdef');

        $json = $type->convertToDatabaseValue([Adjustment::of(AdjustmentType::Option, 'Name', Money::of(500, Currency::of('CAD')), $line, origin: 'option:name')], $platform);

        self::assertSame('[{"type":"option","label":"Name","amount":500,"currency":"CAD","line":{"type":"product","id":"shirt","configuration":"0123456789abcdef"},"neutral":false,"origin":"option:name"}]', $json);
        self::assertTrue($type->convertToPHPValue($json, $platform)[0]->line?->equals($line));
    }

    #[DataProvider('unreadableLines')]
    public function testLinesNoCodeWroteAreRefused(mixed $stored): void
    {
        $this->expectException(ValueNotConvertible::class);

        new OrderLinesType()->convertToPHPValue($stored, new SQLitePlatform());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unreadableAdjustments(): iterable
    {
        $valid = ['type' => 'tax', 'label' => 'GST', 'amount' => 5, 'currency' => 'CAD', 'line' => null, 'neutral' => false];

        yield 'not a string' => [null];
        yield 'not JSON' => ['['];
        yield 'an object' => ['{"a":1}'];
        yield 'an object of valid adjustments' => [json_encode(['first' => $valid])];
        yield 'an adjustment that is not an object' => ['["tax"]'];
        yield 'an unknown type' => [json_encode([[...$valid, 'type' => 'discount']])];
        yield 'a type that is not a string' => [json_encode([[...$valid, 'type' => 1]])];

        foreach (['type', 'label', 'amount', 'currency', 'neutral'] as $key) {
            $missing = $valid;
            unset($missing[$key]);

            yield 'no '.$key => [json_encode([$missing])];
        }

        yield 'a label of the wrong type' => [json_encode([[...$valid, 'label' => 1]])];
        yield 'an amount of the wrong type' => [json_encode([[...$valid, 'amount' => '5']])];
        yield 'a currency of the wrong type' => [json_encode([[...$valid, 'currency' => 1]])];
        yield 'a neutral flag of the wrong type' => [json_encode([[...$valid, 'neutral' => 0]])];
        yield 'a line that is not an object' => [json_encode([[...$valid, 'line' => 'product:1']])];
        yield 'a line without a type' => [json_encode([[...$valid, 'line' => ['id' => '1']]])];
        yield 'a line without an id' => [json_encode([[...$valid, 'line' => ['type' => 'product']]])];
        yield 'a refused line' => [json_encode([[...$valid, 'line' => ['type' => 'Product', 'id' => '1']]])];
        yield 'a fingerprint of the wrong type' => [json_encode([[...$valid, 'line' => ['type' => 'product', 'id' => '1', 'configuration' => 1]]])];
        yield 'a refused fingerprint' => [json_encode([[...$valid, 'line' => ['type' => 'product', 'id' => '1', 'configuration' => 'abc']]])];
        yield 'a blank label' => [json_encode([[...$valid, 'label' => ' ']])];
        yield 'an unknown currency' => [json_encode([[...$valid, 'currency' => 'XYZ']])];
        yield 'an origin of the wrong type' => [json_encode([[...$valid, 'origin' => 1]])];
        yield 'a refused origin' => [json_encode([[...$valid, 'origin' => 'a b']])];
    }

    #[DataProvider('unreadableAdjustments')]
    public function testAdjustmentsNoCodeWroteAreRefused(mixed $stored): void
    {
        $this->expectException(ValueNotConvertible::class);

        new AdjustmentsType()->convertToPHPValue($stored, new SQLitePlatform());
    }

    /**
     * The refusal of a value object is kept as the cause, so the log says what was wrong with the row.
     */
    public function testTheCauseOfARefusalIsKept(): void
    {
        try {
            new OrderLinesType()->convertToPHPValue('[{"type":"product","id":"1","description":"D","quantity":0,"unit_price":1,"currency":"CAD","tax_category":"g"}]', new SQLitePlatform());
            self::fail('The line was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(InvalidValue::class, $valueNotConvertible->getPrevious());
        }

        try {
            new AdjustmentsType()->convertToPHPValue('[{"type":"fee","label":"F","amount":1,"currency":"XYZ","line":null,"neutral":false}]', new SQLitePlatform());
            self::fail('The adjustment was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(UnknownCurrency::class, $valueNotConvertible->getPrevious());
        }
    }
}
