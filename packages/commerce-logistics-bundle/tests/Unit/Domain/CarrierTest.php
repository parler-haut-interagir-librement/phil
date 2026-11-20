<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Label;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Parcel;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingAddress;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingMargin;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Parcel::class)]
#[CoversClass(ShippingAddress::class)]
#[CoversClass(RateRequest::class)]
#[CoversClass(LabelRequest::class)]
#[CoversClass(RateQuote::class)]
#[CoversClass(Label::class)]
#[CoversClass(ShippingMargin::class)]
#[CoversClass(ShippingCost::class)]
#[CoversClass(CarrierRefused::class)]
final class CarrierTest extends TestCase
{
    public function testAParcelWithOrWithoutItsDimensions(): void
    {
        $parcel = Parcel::of(1_250, 300, 200, 100);

        self::assertSame([1_250, 300, 200, 100, true], [$parcel->grams, $parcel->lengthMillimetres, $parcel->widthMillimetres, $parcel->heightMillimetres, $parcel->hasDimensions()]);
        self::assertFalse(Parcel::of(1)->hasDimensions());
        self::assertSame(1_000_000, Parcel::of(1_000_000, 10_000, 1, 10_000)->grams);
    }

    /**
     * @return iterable<string, array{int, ?int, ?int, ?int, string}>
     */
    public static function parcels(): iterable
    {
        yield 'weightless' => [0, null, null, null, 'A parcel weighs 1 to 1000000 grams; 0 is refused.'];
        yield 'too heavy' => [1_000_001, null, null, null, 'A parcel weighs 1 to 1000000 grams; 1000001 is refused.'];
        yield 'two dimensions' => [1, 1, 1, null, 'A parcel gives its three dimensions, or none.'];
        yield 'one dimension' => [1, null, null, 1, 'A parcel gives its three dimensions, or none.'];
        yield 'a flat side' => [1, 1, 0, 1, 'A side of a parcel measures 1 to 10000 millimetres; 0 is refused.'];
        yield 'a side too long' => [1, 10_001, 1, 1, 'A side of a parcel measures 1 to 10000 millimetres; 10001 is refused.'];
    }

    #[DataProvider('parcels')]
    public function testAParcelItCannotPrice(int $grams, ?int $length, ?int $width, ?int $height, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Parcel::of($grams, $length, $width, $height);
    }

    public function testAnAddressIsCompleteForALabel(): void
    {
        $address = ShippingAddress::of('CA', 'k2b 8j6', ' Boutique ', [' 2701 Riverside ', '', 'Suite 2'], ' Ottawa ', ' ON ', ' 613 ');

        self::assertSame(['CA', 'K2B8J6', 'Boutique', ['2701 Riverside', 'Suite 2'], 'Ottawa', 'ON', '613'], [$address->country, $address->postalCode, $address->name, $address->street, $address->city, $address->region, $address->phone]);
        self::assertTrue($address->isComplete());
        self::assertFalse(ShippingAddress::of('CA', 'K2B8J6')->isComplete());
        self::assertFalse(ShippingAddress::of('CA', 'K2B8J6', 'Name', ['Street'])->isComplete());
        self::assertFalse(ShippingAddress::of('CA', 'K2B8J6', 'Name', [], 'City')->isComplete());
        self::assertFalse(ShippingAddress::of('CA', 'K2B8J6', '', ['Street'], 'City')->isComplete());
        self::assertFalse(ShippingAddress::of('CA', '', 'Name', ['Street'], 'City')->isComplete());
        self::assertFalse(ShippingAddress::of('US', '', 'Name', ['Street'], 'City')->isComplete());
        self::assertTrue(ShippingAddress::of('IE', '', 'Name', ['Street'], 'Dublin')->isComplete());
        self::assertSame(str_repeat('é', 128), ShippingAddress::of('FR', '75001', str_repeat('é', 128))->name);
    }

    /**
     * @return iterable<string, array{string, string, list<string>, string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'a country in lowercase' => ['ca', 'K2B8J6', [], '', '"ca" is not a country: two capital letters, as ISO 3166-1 gives them.'];
        yield 'a country of three letters' => ['CAN', 'K2B8J6', [], '', '"CAN" is not a country: two capital letters, as ISO 3166-1 gives them.'];
        yield 'a country ending in a line break' => ["CA\n", 'K2B8J6', [], '', "\"CA\n\" is not a country: two capital letters, as ISO 3166-1 gives them."];
        yield 'a postal code with a symbol' => ['CA', 'K2B#8J6', [], '', 'A postal code is up to 16 letters, digits or "-".'];
        yield 'a postal code too long' => ['CA', str_repeat('1', 17), [], '', 'A postal code is up to 16 letters, digits or "-".'];
        yield 'a postal code ending in a line break' => ['CA', "K2B8J6\n", [], '', 'A postal code is up to 16 letters, digits or "-".'];
        yield 'four street lines' => ['CA', 'K2B8J6', ['a', 'b', 'c', 'd'], '', 'An address has up to three street lines.'];
        yield 'a name on two lines' => ['CA', 'K2B8J6', [], "Name\nInjected", 'A field of an address is up to 128 characters on one line.'];
        yield 'a street line too long' => ['CA', 'K2B8J6', [str_repeat('s', 129)], '', 'A field of an address is up to 128 characters on one line.'];
        yield 'a name too long' => ['CA', 'K2B8J6', [], str_repeat('n', 129), 'A field of an address is up to 128 characters on one line.'];
        yield 'a name with a delete character' => ['CA', 'K2B8J6', [], "Name\x7F", 'A field of an address is up to 128 characters on one line.'];
    }

    /**
     * @param list<string> $street
     */
    #[DataProvider('addresses')]
    public function testAnAddressItRefuses(string $country, string $postalCode, array $street, string $name, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShippingAddress::of($country, $postalCode, $name, $street);
    }

    public function testALabelRequest(): void
    {
        $from = ShippingAddress::of('CA', 'K2B8J6', 'Shop', ['1 Street'], 'Ottawa');
        $to = ShippingAddress::of('CA', 'J0E1X0', 'Buyer', ['2 Street'], 'Mont-Tremblant');
        $request = LabelRequest::of($from, $to, Parcel::of(10), str_repeat('S', 32), str_repeat('r', 35));

        self::assertSame([$from, $to, 10, str_repeat('S', 32), str_repeat('r', 35)], [$request->from, $request->to, $request->parcel->grams, $request->service, $request->reference]);
        self::assertSame('', LabelRequest::of($from, $to, Parcel::of(10), 'DOM.RP')->reference);

        $rate = new RateRequest($from, $to, Parcel::of(10));
        self::assertSame([$from, $to], [$rate->from, $rate->to]);
    }

    /**
     * @return iterable<string, array{bool, bool, string, string, string}>
     */
    public static function labelRequests(): iterable
    {
        yield 'from an incomplete address' => [false, true, 'DOM.RP', '', 'A label goes from a complete address to a complete address: a name, a street, a city and a postal code.'];
        yield 'to an incomplete address' => [true, false, 'DOM.RP', '', 'A label goes from a complete address to a complete address: a name, a street, a city and a postal code.'];
        yield 'no service' => [true, true, '', '', '"" is not a service code: 1 to 32 visible ASCII characters.'];
        yield 'a service too long' => [true, true, str_repeat('S', 33), '', \sprintf('"%s" is not a service code: 1 to 32 visible ASCII characters.', str_repeat('S', 33))];
        yield 'a service ending in a line break' => [true, true, "DOM.RP\n", '', "\"DOM.RP\n\" is not a service code: 1 to 32 visible ASCII characters."];
        yield 'a reference too long' => [true, true, 'DOM.RP', str_repeat('r', 36), 'A reference printed on a label is up to 35 printable ASCII characters.'];
        yield 'a reference on two lines' => [true, true, 'DOM.RP', "a\nb", 'A reference printed on a label is up to 35 printable ASCII characters.'];
        yield 'a reference ending in a line break' => [true, true, 'DOM.RP', "ORDER-1\n", 'A reference printed on a label is up to 35 printable ASCII characters.'];
    }

    #[DataProvider('labelRequests')]
    public function testALabelRequestItRefuses(bool $completeFrom, bool $completeTo, string $service, string $reference, string $message): void
    {
        $complete = ShippingAddress::of('CA', 'K2B8J6', 'Shop', ['1 Street'], 'Ottawa');
        $incomplete = ShippingAddress::of('CA', 'K2B8J6');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        LabelRequest::of($completeFrom ? $complete : $incomplete, $completeTo ? $complete : $incomplete, Parcel::of(10), $service, $reference);
    }

    public function testAMarginCanBeALoss(): void
    {
        $cad = Currency::of('CAD');

        self::assertSame(300, new ShippingMargin(Money::of(1_500, $cad), Money::of(1_200, $cad))->margin()->minorUnits);
        self::assertSame(-200, new ShippingMargin(Money::zero($cad), Money::of(200, $cad))->margin()->minorUnits);
    }

    public function testACostIsRecordedForAShipment(): void
    {
        $shipment = Shipment::of('ORDER-1', ['product:A' => 1], new DateTimeImmutable('2026-11-11'));
        $at = new DateTimeImmutable('2026-11-11 12:00');
        $cost = ShippingCost::of($shipment, 'canada_post', 'DOM.RP', Money::of(1_266, Currency::of('CAD')), $at, '1234567890123456');

        self::assertSame([$shipment->id, 'ORDER-1', 'canada_post', 'DOM.RP', '1234567890123456', 1_266, 'CAD', $at], [$cost->shipment, $cost->orderNumber, $cost->carrier, $cost->service, $cost->tracking, $cost->cost->minorUnits, $cost->cost->currency->code, $cost->recordedAt]);
        self::assertNull(ShippingCost::of($shipment, 'Post office', 'counter', Money::zero(Currency::of('CAD')), $at)->tracking);
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, string}>
     */
    public static function costs(): iterable
    {
        yield 'no carrier' => ['', 'DOM.RP', 1, null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'a carrier too long' => [str_repeat('c', 65), 'DOM.RP', 1, null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'a carrier ending in a line break' => ["canada_post\n", 'DOM.RP', 1, null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'no service' => ['canada_post', '', 1, null, '"" is not a service code: 1 to 32 visible ASCII characters.'];
        yield 'a service with a space' => ['canada_post', 'DOM RP', 1, null, '"DOM RP" is not a service code: 1 to 32 visible ASCII characters.'];
        yield 'a service ending in a line break' => ['canada_post', "DOM.RP\n", 1, null, "\"DOM.RP\n\" is not a service code: 1 to 32 visible ASCII characters."];
        yield 'an empty tracking number' => ['canada_post', 'DOM.RP', 1, '', 'A tracking number is 1 to 128 visible ASCII characters.'];
        yield 'a tracking number too long' => ['canada_post', 'DOM.RP', 1, str_repeat('t', 129), 'A tracking number is 1 to 128 visible ASCII characters.'];
        yield 'a tracking number ending in a line break' => ['canada_post', 'DOM.RP', 1, "1234567890123456\n", 'A tracking number is 1 to 128 visible ASCII characters.'];
        yield 'a negative cost' => ['canada_post', 'DOM.RP', -1, null, 'costs zero or more.'];
    }

    #[DataProvider('costs')]
    public function testACostItRefuses(string $carrier, string $service, int $cost, ?string $tracking, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShippingCost::of(Shipment::of('ORDER-1', ['product:A' => 1], new DateTimeImmutable()), $carrier, $service, Money::of($cost, Currency::of('CAD')), new DateTimeImmutable(), $tracking);
    }

    public function testTheLimitsOfACostAreAccepted(): void
    {
        $cost = ShippingCost::of(Shipment::of('O', ['product:A' => 1], new DateTimeImmutable()), str_repeat('c', 64), str_repeat('s', 32), Money::zero(Currency::of('CAD')), new DateTimeImmutable(), str_repeat('t', 128));

        self::assertSame([64, 32, 128], [\strlen($cost->carrier), \strlen($cost->service), \strlen((string) $cost->tracking)]);
    }

    public function testTheRefusalsOfCarriers(): void
    {
        $previous = new RuntimeException('cause');

        self::assertSame('No carrier is known as "post".', CarrierRefused::unknownCarrier('post')->getMessage());
        self::assertSame($previous, CarrierRefused::unavailable('post', 'down.', $previous)->getPrevious());
        self::assertSame('The carrier post did not answer: down.', CarrierRefused::unavailable('post', 'down.')->getMessage());
        self::assertSame(0, CarrierRefused::unavailable('post', 'down.')->getCode());
        self::assertSame('The cost of the shipment s-1 is already recorded.', CarrierRefused::alreadyRecorded('s-1')->getMessage());
        self::assertSame("A parcel of O-1 cost USD, a currency other than the order's.", CarrierRefused::otherCurrency('O-1', 'USD')->getMessage());
    }
}
