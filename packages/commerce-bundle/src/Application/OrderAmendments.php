<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Domain\Exception\QuoteRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\Money\Money;

/**
 * Changing a placed order: it becomes a cart again, is changed with the `OrderModifier`, and is placed anew; its
 * payments stay. What it then lacks is paid like any balance; what was paid beyond its new total is `overpaid()`, for
 * the application to refund.
 *
 * An order whose receipt is issued no longer changes: a credit note and a new order replace it. Releasing and
 * reserving its stock again is the logistics module's.
 */
final readonly class OrderAmendments
{
    public function __construct(
        private Payments $payments,
        private ReceiptRepository $receipts,
    ) {
    }

    public function reopen(Order $order): void
    {
        if ($this->receipts->receiptOf($order->number) instanceof Receipt) {
            throw QuoteRefused::receiptIssued($order->number);
        }

        $order->reopen();
    }

    /**
     * What the order's payments hold beyond its total, once amended down.
     */
    public function overpaid(Order $order): Money
    {
        return $this->payments->summary($order)->overpaid();
    }
}
