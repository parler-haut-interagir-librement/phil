<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Application\Delivery;

/**
 * What a message carries is an identifier only: the application turns it back into the recipients and the values of
 * the email — its claim check — when the message is handled, so that no address and no value waits in a queue.
 */
interface DeliveryResolver
{
    /**
     * @param string $reference the identifier the message carries: an order, a ticket, a registration…
     *
     * @return Delivery|null `null` when there is nothing to send any more, as for a cancelled order
     */
    public function resolve(string $template, string $reference): ?Delivery;
}
