<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Port;

use Phil\CommerceLogisticsBundle\Domain\Carrier\Label;
use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;

/**
 * A carrier's rates and labels: what it would charge the shop for a parcel, and the label it produces or sells for
 * one. It never sets what the buyer pays for delivery, which is the delivery method's price.
 *
 * Each carrier is an adapter of its own, named after it; `Infrastructure\CanadaPost\CanadaPostCarrier` is the first.
 * A service implementing it is tagged `phil_commerce_logistics.carrier` by autoconfiguration.
 */
interface CarrierGateway
{
    /**
     * The carrier's code, which `ShippingLabels` and the recorded costs name it by: `canada_post`, for instance.
     */
    public function carrier(): string;

    /**
     * @return list<RateQuote> the carrier's services for the parcel, each with its cost
     *
     * @throws CarrierRefused when the carrier refuses the request or does not answer
     */
    public function rates(RateRequest $request): array;

    /**
     * Buys or produces the label, which the carrier charges the shop for.
     *
     * @throws CarrierRefused when the carrier refuses the request or does not answer
     */
    public function label(LabelRequest $request): Label;
}
