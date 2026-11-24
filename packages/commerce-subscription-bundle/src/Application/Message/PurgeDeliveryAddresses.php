<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application\Message;

/**
 * Asks for the purge of the delivery addresses kept past their retention. It carries nothing: the handler reads the
 * time and the retention itself.
 */
final readonly class PurgeDeliveryAddresses
{
}
