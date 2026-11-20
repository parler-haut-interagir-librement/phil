<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryMethod::class)]
final class DeliveryMethodTest extends TestCase
{
    public function testAMethodHoldsItsTerms(): void
    {
        $method = DeliveryMethod::of('post', 'Postes Canada', DeliveryKind::Shipping, $this->cad(1_200), $this->cad(7_500), ['QC', 'QC', 'ON'], 2, 5, null, 3);

        self::assertSame(['post', 'Postes Canada', DeliveryKind::Shipping], [$method->code, $method->label, $method->kind]);
        self::assertSame(1_200, $method->price->minorUnits);
        self::assertSame('CAD', $method->price->currency->code);
        self::assertSame(7_500, $method->freeFrom()?->minorUnits);
        self::assertSame(['QC', 'ON'], $method->zones);
        self::assertSame([2, 5, null, 3], [$method->minDays, $method->maxDays, $method->pickupLocation, $method->priority]);
        self::assertTrue($method->isActive());

        $pickup = DeliveryMethod::of('pickup-mtl', 'Ramassage Montréal', DeliveryKind::Pickup, Money::zero(Currency::of('CAD')), pickupLocation: 'mtl');
        self::assertSame('mtl', $pickup->pickupLocation);
        self::assertNull($pickup->freeFrom());
        self::assertSame([[], 0, 0, 0], [$pickup->zones, $pickup->minDays, $pickup->maxDays, $pickup->priority]);
        self::assertSame(365, DeliveryMethod::of('slow', 'Slow', DeliveryKind::LocalDelivery, $this->cad(1), minDays: 365, maxDays: 365)->maxDays);
    }

    public function testAMethodServesItsZonesInItsCurrency(): void
    {
        $method = DeliveryMethod::of('post', 'Post', DeliveryKind::Shipping, $this->cad(1_200), zones: ['QC', 'ON']);
        $everywhere = DeliveryMethod::of('world', 'World', DeliveryKind::Shipping, $this->cad(5_000));

        self::assertTrue($method->serves('ON', Currency::of('CAD')));
        self::assertFalse($method->serves('BC', Currency::of('CAD')));
        self::assertFalse($method->serves(null, Currency::of('CAD')));
        self::assertFalse($method->serves('QC', Currency::of('USD')));
        self::assertTrue($everywhere->serves(null, Currency::of('CAD')));

        $method->withdraw();
        self::assertFalse($method->isActive());
        self::assertFalse($method->serves('QC', Currency::of('CAD')));
        $method->restore();
        self::assertTrue($method->serves('QC', Currency::of('CAD')));
    }

    public function testItIsFreeFromItsThreshold(): void
    {
        $method = DeliveryMethod::of('post', 'Post', DeliveryKind::Shipping, $this->cad(1_200), $this->cad(7_500));

        self::assertSame(1_200, $method->costFor($this->cad(7_499))->minorUnits);
        self::assertTrue($method->costFor($this->cad(7_500))->isZero());
        self::assertSame('CAD', $method->costFor($this->cad(7_500))->currency->code);
        self::assertSame(1_200, DeliveryMethod::of('x', 'X', DeliveryKind::Shipping, $this->cad(1_200))->costFor($this->cad(1_000_000))->minorUnits);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidMethods(): iterable
    {
        yield 'uppercase code' => [['code' => 'Post'], '"Post" is not a delivery method code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'code too long' => [['code' => str_repeat('a', 65)], 'is not a delivery method code'];
        yield 'code with a trailing newline' => [['code' => "post\n"], 'is not a delivery method code'];
        yield 'blank label' => [['label' => ' '], 'The delivery method post has a label, which the receipt shows.'];
        yield 'negative price' => [['price' => -1], 'The delivery method post costs zero or more, and is free from an amount of its currency.'];
        yield 'negative threshold' => [['free' => -1], 'The delivery method post costs zero or more'];
        yield 'threshold in another currency' => [['freeCurrency' => 'USD'], 'The delivery method post costs zero or more'];
        yield 'zone with a space' => [['zones' => ['Q C']], '"Q C" is not a zone: 1 to 64 visible ASCII characters.'];
        yield 'zone too long' => [['zones' => [str_repeat('z', 65)]], 'is not a zone'];
        yield 'zone with a trailing newline' => [['zones' => ["QC\n"]], 'is not a zone'];
        yield 'negative days' => [['min' => -1], 'The delivery method post takes 0 to 365 days, its least before its most.'];
        yield 'most before least' => [['min' => 3, 'max' => 2], 'The delivery method post takes 0 to 365 days'];
        yield 'beyond a year' => [['max' => 366], 'The delivery method post takes 0 to 365 days'];
        yield 'pickup without its location' => [['kind' => DeliveryKind::Pickup], 'A pickup names its location, and only a pickup does: post.'];
        yield 'shipping with a location' => [['pickup' => 'mtl'], 'A pickup names its location, and only a pickup does: post.'];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidMethods')]
    public function testAnInvalidMethodIsRefused(array $override, string $message): void
    {
        /** @var array{code: string, label: string, kind: DeliveryKind, price: int, free: ?int, freeCurrency: string, zones: list<string>, min: int, max: int, pickup: ?string} $values */
        $values = [...['code' => 'post', 'label' => 'Post', 'kind' => DeliveryKind::Shipping, 'price' => 100, 'free' => 1_000, 'freeCurrency' => 'CAD', 'zones' => [], 'min' => 0, 'max' => 0, 'pickup' => null], ...$override];

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        DeliveryMethod::of($values['code'], $values['label'], $values['kind'], $this->cad($values['price']), null === $values['free'] ? null : Money::of($values['free'], Currency::of($values['freeCurrency'])), $values['zones'], $values['min'], $values['max'], $values['pickup']);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
