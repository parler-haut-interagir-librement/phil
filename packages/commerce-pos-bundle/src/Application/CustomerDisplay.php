<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;

/**
 * The customer display: what the screen turned to the customer shows of the cart, as the cashier builds it and while
 * it is paid. The screen is the application's — a second tablet, opened by a QR code, fed by Mercure —; the module
 * gives what it shows.
 */
final readonly class CustomerDisplay
{
    public function __construct(
        private Payments $payments,
    ) {
    }

    public function show(Order $order): DisplayedCart
    {
        $lines = [];

        foreach ($order->lines() as $line) {
            $lines[] = ['description' => $line->description, 'quantity' => $line->quantity, 'unitPrice' => $line->unitPrice, 'subtotal' => $line->subtotal()];
        }

        $tax = $order->taxIncluded() ? $order->includedTax() : $order->adjustmentsTotal(AdjustmentType::Tax);
        $discounts = $order->adjustmentsTotal(AdjustmentType::Promotion)->abs();
        $payments = $this->payments->summary($order);

        return new DisplayedCart($order->number, $lines, $order->itemsTotal(), $discounts, $tax, $order->taxIncluded(), $order->adjustmentsTotal(AdjustmentType::Tip), $order->total(), $payments->net(), $payments->payable());
    }
}
