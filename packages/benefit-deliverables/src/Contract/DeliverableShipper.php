<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

use Phil\BenefitDeliverables\Shipping\ShipmentReceipt;
use Phil\BenefitDeliverables\Shipping\ShipmentRequest;

/**
 * Hands a deliverable to a carrier through its API, rather than through a file: an adapter per carrier implements it.
 *
 * The package ships no adapter. The request holds what the label needs, never the member's email address nor their
 * tier.
 */
interface DeliverableShipper
{
    public function ship(ShipmentRequest $request): ShipmentReceipt;
}
