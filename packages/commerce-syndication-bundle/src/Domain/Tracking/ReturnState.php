<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Tracking;

/**
 * Where a return between a reseller and its supplier stands.
 */
enum ReturnState: string
{
    /** The reseller announced it; the goods are on their way to the supplier. */
    case Requested = 'requested';

    /** The supplier received the goods and credited what it accepted. */
    case Received = 'received';
}
