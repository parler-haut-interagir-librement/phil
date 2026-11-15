<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Order;

/**
 * Where an order stands. Only a cart changes; a quoted order waits for the buyer at its frozen prices; a placed order
 * is what the buyer agreed to, and only its state moves on from there — or back to a cart, to be amended.
 *
 *     cart → quoted → placed → cancelled
 *     cart → placed; quoted → cart; placed → cart; cart, quoted → cancelled
 */
enum OrderState: string
{
    /** Being built: lines, quantities, prices and adjustments change with each step of the buyer. */
    case Cart = 'cart';

    /** Offered to the buyer as a quote: lines and adjustments are frozen until it is accepted, or goes back to a cart. */
    case Quoted = 'quoted';

    /** Agreed to by the buyer: lines and adjustments are frozen, the total is what payment will ask for. */
    case Placed = 'placed';

    /** Given up, as a cart or once placed: nothing changes any more. */
    case Cancelled = 'cancelled';
}
