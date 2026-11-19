<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;

final class RecordingTrackingHandler implements PartnerShipmentHandler, PartnerReturnHandler
{
    /** @var list<string> */
    public array $calls = [];

    #[Override]
    public function shipped(PartnerShipment $shipment): void
    {
        $this->calls[] = 'shipped '.$shipment->shipment;
    }

    #[Override]
    public function requested(PartnerReturn $return): void
    {
        $this->calls[] = 'requested '.$return->returnNumber;
    }

    #[Override]
    public function received(PartnerReturn $return): void
    {
        $this->calls[] = 'received '.$return->returnNumber;
    }
}
