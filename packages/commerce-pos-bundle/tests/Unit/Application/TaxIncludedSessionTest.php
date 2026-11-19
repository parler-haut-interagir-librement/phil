<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\CustomerDisplay;
use Phil\CommercePosBundle\Application\OfflineSale;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A day at a fair: the price on the tag is the price paid, and the taxes are taken out of it.
 */
#[CoversClass(CashSessions::class)]
#[CoversClass(CashSession::class)]
#[CoversClass(RegisterCheckout::class)]
#[CoversClass(OfflineSales::class)]
#[CoversClass(CustomerDisplay::class)]
#[CoversClass(PosRefused::class)]
final class TaxIncludedSessionTest extends TestCase
{
    private Till $till;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->till = new Till([new TaxOrderProcessor(new ConfiguredTaxRates(['standard' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]))]);
        $this->at = new DateTimeImmutable('2026-11-04 10:00:00');
    }

    /**
     * A 12.00 mug is 10.44 before taxes, GST 0.52 and QST 1.04 included: the customer pays 12.00.
     */
    public function testACartOfTheSessionIsSoldAtThePriceOnTheTag(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at, true);
        $order = $this->cart();

        $this->till->checkout->start($order, $session);
        $this->till->modifier->add($order, SellableReference::of('product', 'MUG'), 1, PricingContext::of(1, $this->at));

        self::assertTrue($session->taxIncluded);
        self::assertTrue($order->taxIncluded());
        self::assertSame([52, 104], array_map(static fn (Adjustment $tax): int => $tax->amount->minorUnits, $order->adjustments(AdjustmentType::Tax)));
        self::assertSame([1_200, 156], [$order->total()->minorUnits, $order->includedTax()->minorUnits]);
        self::assertSame(1_044, $this->till->checkout->tipShare($order, 1_000_000)->minorUnits);
        self::assertSame(157, $this->till->checkout->tipShare($order, 150_000)->minorUnits);

        $this->till->checkout->ring($order, $session, 'ada', $this->at);
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame(1_357, $order->total()->minorUnits);
    }

    public function testASessionAddsTheTaxesByDefault(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
        $order = $this->cart();
        $order->changeTaxIncluded(true);

        $this->till->checkout->start($order, $session);
        $this->till->modifier->add($order, SellableReference::of('product', 'MUG'), 1, PricingContext::of(1, $this->at));

        self::assertFalse($session->taxIncluded);
        self::assertFalse($order->taxIncluded());
        self::assertSame([1_380, 0], [$order->total()->minorUnits, $order->includedTax()->minorUnits]);
        self::assertSame(1_200, $this->till->checkout->tipShare($order, 1_000_000)->minorUnits);
    }

    /**
     * The customer display shows the taxes a cart adds, or those its price includes.
     */
    public function testTheCustomerDisplayShowsTheTaxesAddedOrIncluded(): void
    {
        $added = $this->cart();
        $this->till->checkout->start($added, $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at));
        $this->till->modifier->add($added, SellableReference::of('product', 'MUG'), 1, PricingContext::of(1, $this->at));

        $shown = $this->till->display->show($added);
        self::assertSame([180, false, 1_380], [$shown->tax->minorUnits, $shown->taxIncluded, $shown->total->minorUnits]);

        $this->till->registers->create('fair', 'Fair', Currency::of('CAD'));
        $included = $this->cart('B-1');
        $this->till->checkout->start($included, $this->till->sessions->open('fair', 'bob', $this->cad(0), $this->at, true));
        $this->till->modifier->add($included, SellableReference::of('product', 'MUG'), 1, PricingContext::of(1, $this->at));

        $shown = $this->till->display->show($included);
        self::assertSame([156, true, 1_200], [$shown->tax->minorUnits, $shown->taxIncluded, $shown->total->minorUnits]);
    }

    /**
     * A cart priced otherwise than its session would show the customer one total and charge another.
     */
    public function testACartNotPricedAsTheSessionIsNotRungUp(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at, true);
        $order = $this->till->cart(['MUG' => 1]);

        try {
            $this->till->checkout->ring($order, $session, 'ada', $this->at);
            self::fail('A cart priced otherwise was rung up.');
        } catch (PosRefused $posRefused) {
            self::assertSame(\sprintf('The order A-1 is not priced as the session %s sells, taxes included or added: start it in the session first.', $session->id), $posRefused->getMessage());
        }

        self::assertSame(OrderState::Cart, $order->state());
        self::assertSame([], $this->till->pos->salesOf($session->id));
    }

    public function testOnlyACartIsStartedInAnOpenSession(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at, true);
        $placed = $this->till->cart(['MUG' => 1], 'B-1');
        $placed->changeTaxIncluded(true);

        $this->till->checkout->ring($placed, $session, 'ada', $this->at);

        foreach ([[$placed, $session, 'The order B-1 is not a cart'], [$this->cart(), $this->closed($session), \sprintf('The session %s is closed.', $session->id)]] as [$order, $in, $message]) {
            try {
                $this->till->checkout->start($order, $in);
                self::fail($message);
            } catch (PosRefused $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
                self::assertFalse(OrderState::Cart === $order->state() && $order->taxIncluded());
            }
        }
    }

    public function testASaleMadeOfflineIsPricedAsItsSession(): void
    {
        $session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at, true);
        $sale = OfflineSale::fromArray([
            'id' => 'sale-1',
            'session' => $session->id,
            'by' => 'ada',
            'rung_at' => '2026-11-04T10:30:00-05:00',
            'total' => 1_200,
            'tip' => 0,
            'lines' => [['type' => 'product', 'id' => 'MUG', 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1_200]],
        ]);

        [$imported] = $this->till->offline->import($sale, $this->at);
        $order = $this->till->orders->find('sale-1');

        self::assertInstanceOf(Order::class, $order);
        self::assertTrue($order->taxIncluded());
        self::assertSame([1_200, true], [$order->total()->minorUnits, $imported->agrees()]);
    }

    private function cart(string $number = 'A-1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->changeTaxZone('QC');

        $this->till->orders->add($order);

        return $order;
    }

    private function closed(CashSession $session): CashSession
    {
        $this->till->sessions->close($session->id, $this->cad(0), 'ada', $this->at);

        return $session;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
