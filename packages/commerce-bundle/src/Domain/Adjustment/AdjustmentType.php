<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Adjustment;

/**
 * What an adjustment is. Each `OrderProcessor` owns one type and replaces all the adjustments of that type each
 * time it runs.
 */
enum AdjustmentType: string
{
    /** What the options chosen on a line add to it, computed by `OptionSupplementProcessor`. */
    case Option = 'option';

    /** A discount, negative: computed by the promotions of the catalogue module. */
    case Promotion = 'promotion';

    /**
     * The unused part of a period, credited or left uncharged when a subscription changes tier at once: computed by
     * the subscription module, on the line, and taxed with it.
     */
    case Proration = 'proration';

    /** A tax line, computed by `TaxOrderProcessor` on the line after its promotions and its proration. */
    case Tax = 'tax';

    /** A delivery charge, computed by the logistics module. */
    case Shipping = 'shipping';

    /** Any other charge or credit an application adds: a handling fee, a store credit. */
    case Fee = 'fee';

    /** A tip, added to the order before it is paid, at the register for instance; not taxed, since it is on no line. */
    case Tip = 'tip';
}
