<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Money;

/**
 * What a line of the module is worth on an order, read the same way by the proration and by the services that record
 * what was ordered.
 */
final class OrderedLine
{
    /** The origin of the proration that leaves the time already used uncharged. */
    public const string UNUSED_TIME = 'proration:time';

    /** The origin of the credit for the unused part of the period changed from. */
    public const string CHANGE_CREDIT = 'proration:credit';

    /** The origin of the credit carried over from an earlier change. */
    public const string CARRIED_CREDIT = 'proration:carried';

    private function __construct()
    {
    }

    /**
     * The line's subtotal with what its options add and after its promotions, before proration and taxes: the price
     * of what it sells, as the buyer is charged it.
     */
    public static function base(Order $order, SellableReference $reference): Money
    {
        $base = $order->line($reference)?->subtotal() ?? Money::zero($order->currency);

        foreach ([AdjustmentType::Option, AdjustmentType::Promotion] as $type) {
            foreach ($order->lineAdjustments($reference, $type) as $adjustment) {
                $base = $base->add($adjustment->amount);
            }
        }

        return $base;
    }

    /**
     * The credit the line takes off — for a change and carried over —, as a positive amount.
     */
    public static function credit(Order $order, SellableReference $reference): Money
    {
        $credit = Money::zero($order->currency);

        foreach ($order->lineAdjustments($reference, AdjustmentType::Proration) as $adjustment) {
            if (\in_array($adjustment->origin, [self::CHANGE_CREDIT, self::CARRIED_CREDIT], true)) {
                $credit = $credit->subtract($adjustment->amount);
            }
        }

        return $credit;
    }
}
