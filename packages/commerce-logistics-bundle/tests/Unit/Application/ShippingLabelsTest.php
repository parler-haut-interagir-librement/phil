<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceLogisticsBundle\Application\Port\CarrierGateway;
use Phil\CommerceLogisticsBundle\Application\ShippingLabels;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Label;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Parcel;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingAddress;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingMargin;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingLabels::class)]
#[CoversClass(RateQuote::class)]
#[CoversClass(Label::class)]
#[CoversClass(ShippingMargin::class)]
final class ShippingLabelsTest extends TestCase
{
    private InMemoryLogistics $costs;

    #[Override]
    protected function setUp(): void
    {
        $this->costs = new InMemoryLogistics();
    }

    public function testTheRatesOfEveryCarrierThatAnswersTheCheapestFirst(): void
    {
        $labels = new ShippingLabels([
            $this->carrier('post', [['RP', 1_266], ['XP', 2_107]]),
            $this->carrier('down', null),
            $this->carrier('courier', [['GROUND', 1_500], ['SAVER', 999]]),
        ], $this->costs);

        self::assertSame(
            [['courier', 'SAVER', 999], ['post', 'RP', 1_266], ['courier', 'GROUND', 1_500], ['post', 'XP', 2_107]],
            array_map(static fn (RateQuote $quote): array => [$quote->carrier, $quote->service, $quote->cost->minorUnits], $labels->rates($this->rateRequest())),
        );
        self::assertSame([], new ShippingLabels([], $this->costs)->rates($this->rateRequest()));
    }

    public function testTheRatesOfOneCarrierAreSortedAndItsFailureShows(): void
    {
        $labels = new ShippingLabels([$this->carrier('post', [['XP', 2_107], ['RP', 1_266]]), $this->carrier('down', null)], $this->costs);

        self::assertSame(['RP', 'XP'], array_map(static fn (RateQuote $quote): string => $quote->service, $labels->rates($this->rateRequest(), 'post')));

        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('The carrier down did not answer: closed.');

        $labels->rates($this->rateRequest(), 'down');
    }

    public function testQuotesInSeveralCurrenciesAreGroupedByCurrency(): void
    {
        $usd = new class implements CarrierGateway {
            #[Override]
            public function carrier(): string
            {
                return 'usd';
            }

            #[Override]
            public function rates(RateRequest $request): array
            {
                return [new RateQuote('usd', 'A', 'A', Money::of(100, Currency::of('USD')))];
            }

            #[Override]
            public function label(LabelRequest $request): Label
            {
                throw CarrierRefused::unavailable('usd', 'no labels.');
            }
        };
        $labels = new ShippingLabels([$usd, $this->carrier('post', [['RP', 1_266]])], $this->costs);

        self::assertSame(['CAD', 'USD'], array_map(static fn (RateQuote $quote): string => $quote->cost->currency->code, $labels->rates($this->rateRequest())));
    }

    public function testALabelIsBoughtThenItsCostRecorded(): void
    {
        $labels = new ShippingLabels([$this->carrier('courier', []), $this->carrier('post', [])], $this->costs);
        $label = $labels->label('post', $this->labelRequest());
        $shipment = Shipment::of('ORDER-1', ['product:A' => 1], new DateTimeImmutable('2026-11-11'), 'post', $label->tracking);
        $at = new DateTimeImmutable('2026-11-11 12:00');

        $cost = $labels->recordLabel($shipment, $label, $at);

        self::assertSame(['post', 'RP', 'TRACK-post', 1_266, $at], [$cost->carrier, $cost->service, $cost->tracking, $cost->cost->minorUnits, $cost->recordedAt]);
        self::assertSame($cost, $this->costs->costOf($shipment->id));
        self::assertSame(['%PDF-', 'application/pdf'], [$label->document, $label->mediaType]);

        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The cost of the shipment %s is already recorded.', $shipment->id));

        $labels->record($shipment, 'post', 'RP', Money::of(1, Currency::of('CAD')), $at);
    }

    public function testAnUnknownCarrierIsRefused(): void
    {
        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains('No carrier is known as "nobody".');

        new ShippingLabels([$this->carrier('post', [])], $this->costs)->label('nobody', $this->labelRequest());
    }

    public function testTheMarginOfAnOrdersDelivery(): void
    {
        $cad = Currency::of('CAD');
        $labels = new ShippingLabels([], $this->costs);
        $order = Order::open('ORDER-1', $cad);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Regular', Money::of(1_500, $cad))]);

        self::assertSame([1_500, 0, 1_500], $this->margin($labels->margin($order)));

        $at = new DateTimeImmutable('2026-11-11');
        $labels->record(Shipment::of('ORDER-1', ['product:A' => 1], $at), 'post', 'RP', Money::of(1_266, $cad), $at);
        $labels->record(Shipment::of('ORDER-1', ['product:B' => 1], $at), 'post', 'RP', Money::of(900, $cad), $at, 'T-2');
        $labels->record(Shipment::of('ORDER-2', ['product:A' => 1], $at), 'post', 'RP', Money::of(5_000, $cad), $at);

        self::assertSame([1_500, 2_166, -666], $this->margin($labels->margin($order)));

        $labels->record(Shipment::of('ORDER-1', ['product:C' => 1], $at), 'courier', 'GROUND', Money::of(1, Currency::of('USD')), $at);

        $this->expectException(CarrierRefused::class);
        $this->expectExceptionMessageIsOrContains("A parcel of ORDER-1 cost USD, a currency other than the order's.");

        $labels->margin($order);
    }

    /**
     * @return array{int, int, int}
     */
    private function margin(ShippingMargin $margin): array
    {
        return [$margin->charged->minorUnits, $margin->cost->minorUnits, $margin->margin()->minorUnits];
    }

    /**
     * @param list<array{string, int}>|null $services `null` for a carrier that does not answer
     */
    private function carrier(string $code, ?array $services): CarrierGateway
    {
        return new readonly class($code, $services) implements CarrierGateway {
            /**
             * @param list<array{string, int}>|null $services
             */
            public function __construct(private string $code, private ?array $services)
            {
            }

            #[Override]
            public function carrier(): string
            {
                return $this->code;
            }

            #[Override]
            public function rates(RateRequest $request): array
            {
                if (null === $this->services) {
                    throw CarrierRefused::unavailable($this->code, 'closed.');
                }

                return array_map(fn (array $service): RateQuote => new RateQuote($this->code, $service[0], $service[0].' service', Money::of($service[1], Currency::of('CAD')), 2), $this->services);
            }

            #[Override]
            public function label(LabelRequest $request): Label
            {
                return new Label($this->code, $request->service, 'TRACK-'.$this->code, Money::of(1_266, Currency::of('CAD')), '%PDF-', 'application/pdf');
            }
        };
    }

    private function rateRequest(): RateRequest
    {
        return new RateRequest(ShippingAddress::of('CA', 'K2B8J6'), ShippingAddress::of('CA', 'J0E1X0'), Parcel::of(500));
    }

    private function labelRequest(): LabelRequest
    {
        return LabelRequest::of(
            ShippingAddress::of('CA', 'K2B8J6', 'Shop', ['1 Street'], 'Ottawa'),
            ShippingAddress::of('CA', 'J0E1X0', 'Buyer', ['2 Street'], 'Mont-Tremblant'),
            Parcel::of(500),
            'RP',
        );
    }
}
