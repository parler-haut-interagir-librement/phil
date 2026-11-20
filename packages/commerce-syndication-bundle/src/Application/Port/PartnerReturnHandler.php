<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application\Port;

use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;

/**
 * What the application does as a return moves between a reseller and its supplier: the supplier expects the parcel
 * once it is requested, the reseller refunds its buyer once the supplier received it.
 *
 * A service implementing it is tagged `phil_commerce_syndication.return_handler` by autoconfiguration. Each method
 * runs once per return, in the transaction that records the message.
 */
interface PartnerReturnHandler
{
    /**
     * At the supplier: a reseller announced the return.
     */
    public function requested(PartnerReturn $return): void;

    /**
     * At the reseller: the supplier received the return and credited it.
     */
    public function received(PartnerReturn $return): void;
}
