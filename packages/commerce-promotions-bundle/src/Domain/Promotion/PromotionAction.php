<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Promotion;

/**
 * What a promotion gives.
 */
enum PromotionAction: string
{
    /** A share off each eligible line, in parts per million. */
    case PercentOff = 'percent_off';

    /** An amount off the order, spread over the eligible lines in proportion to their subtotals. */
    case AmountOff = 'amount_off';

    /** N units for the price of X on each eligible line: every group of N units has N − X units free. */
    case BuyNPayX = 'buy_n_pay_x';

    /** A price for each complete bundle of its groups; each product of a bundle keeps its own line and its share. */
    case BundlePrice = 'bundle_price';

    /** The delivery charge waived; the lines keep their prices. */
    case FreeShipping = 'free_shipping';
}
