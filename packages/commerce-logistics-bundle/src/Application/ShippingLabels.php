<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Port\CarrierGateway;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Label;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingMargin;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingCostRepository;
use Phil\Money\Money;

/**
 * The carriers' rates and labels, and what each shipment really cost beside what the buyer paid for delivery. The
 * delivery price stays the delivery method's: nothing here changes an order.
 */
final readonly class ShippingLabels
{
    /**
     * @param iterable<CarrierGateway> $carriers
     */
    public function __construct(
        private iterable $carriers,
        private ShippingCostRepository $costs,
    ) {
    }

    /**
     * The rates of one carrier, or of every carrier — those that do not answer left out —, the cheapest first.
     *
     * @return list<RateQuote>
     *
     * @throws CarrierRefused when the carrier named does not answer, or is not known
     */
    public function rates(RateRequest $request, ?string $carrier = null): array
    {
        if (null !== $carrier) {
            $quotes = $this->carrier($carrier)->rates($request);
        } else {
            $quotes = [];

            foreach ($this->carriers as $gateway) {
                try {
                    array_push($quotes, ...$gateway->rates($request));
                } catch (CarrierRefused) {
                    continue;
                }
            }
        }

        usort($quotes, static fn (RateQuote $a, RateQuote $b): int => [$a->cost->currency->code, $a->cost->minorUnits] <=> [$b->cost->currency->code, $b->cost->minorUnits]);

        return $quotes;
    }

    /**
     * Buys or produces a label from the carrier. Its cost is recorded once the parcel leaves, by `recordLabel()`.
     *
     * @throws CarrierRefused when the carrier does not answer, or is not known
     */
    public function label(string $carrier, LabelRequest $request): Label
    {
        return $this->carrier($carrier)->label($request);
    }

    /**
     * Records what the label of a shipment cost.
     */
    public function recordLabel(Shipment $shipment, Label $label, DateTimeImmutable $at): ShippingCost
    {
        return $this->record($shipment, $label->carrier, $label->service, $label->cost, $at, $label->tracking);
    }

    /**
     * Records what a shipment cost, its label bought elsewhere: at the post office, on the carrier's site.
     *
     * @throws CarrierRefused when the shipment's cost is already recorded
     */
    public function record(Shipment $shipment, string $carrier, string $service, Money $cost, DateTimeImmutable $at, ?string $tracking = null): ShippingCost
    {
        if ($this->costs->costOf($shipment->id) instanceof ShippingCost) {
            throw CarrierRefused::alreadyRecorded($shipment->id);
        }

        $recorded = ShippingCost::of($shipment, $carrier, $service, $cost, $at, $tracking);
        $this->costs->addCost($recorded);

        return $recorded;
    }

    /**
     * What the buyer was charged for delivery against what the order's recorded parcels cost.
     *
     * @throws CarrierRefused when a parcel cost a currency other than the order's
     */
    public function margin(Order $order): ShippingMargin
    {
        $cost = Money::zero($order->currency);

        foreach ($this->costs->costsOf($order->number) as $recorded) {
            if (!$recorded->cost->currency->equals($order->currency)) {
                throw CarrierRefused::otherCurrency($order->number, $recorded->cost->currency->code);
            }

            $cost = $cost->add($recorded->cost);
        }

        return new ShippingMargin($order->adjustmentsTotal(AdjustmentType::Shipping), $cost);
    }

    private function carrier(string $code): CarrierGateway
    {
        foreach ($this->carriers as $gateway) {
            if ($gateway->carrier() === $code) {
                return $gateway;
            }
        }

        throw CarrierRefused::unknownCarrier($code);
    }
}
