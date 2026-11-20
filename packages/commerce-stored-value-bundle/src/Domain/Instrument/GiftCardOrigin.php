<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Instrument;

/**
 * Where the value of a gift card comes from, which decides whether it may expire.
 */
enum GiftCardOrigin: string
{
    /** Given by the shop — a promotion, a gesture, a prize —, with nothing paid for it: it may expire. */
    case Given = 'given';

    /**
     * Paid for by a buyer: it never expires, unless it is for a specific service — a meal, a show, a treatment — and
     * marked so.
     */
    case Paid = 'paid';
}
