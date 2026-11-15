<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Application\Port\OptionDrawer;
use Phil\CommerceBundle\Domain\Exception\DrawRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;

/**
 * Makes the draws of a paid order: each option left to draw, on each line, drawn once per unit by the drawer it
 * names. Nobody knows a drawn value before the payment, not even the store; the application calls it once the
 * order's payments are `Paid`, then stores the order.
 *
 * Running it again draws nothing more: a value drawn is never drawn again.
 */
final readonly class OptionDraws
{
    /**
     * @param iterable<OptionDrawer> $drawers
     */
    public function __construct(
        private iterable $drawers = [],
    ) {
    }

    /**
     * @return int how many options were drawn, on every line together
     *
     * @throws DrawRefused when the order is not paid, or no drawer supports an option's
     */
    public function draw(Order $order, OrderPayments $payments): int
    {
        if (OrderPaymentState::Paid !== $payments->state()) {
            throw DrawRefused::notPaid($order->number, $payments->state());
        }

        $drawn = 0;

        foreach ($order->lines() as $line) {
            foreach ($line->configuration->pending() as $option) {
                $name = (string) $line->configuration->value($option)?->drawer;
                $drawer = $this->drawer($name);
                $draws = [];

                for ($unit = 1; $unit <= $line->quantity; ++$unit) {
                    $draws[] = $drawer->draw($name, $order, $line, $option, $unit);
                }

                $order->recordDraws($line->reference, $option, $draws);
                ++$drawn;
            }
        }

        return $drawn;
    }

    private function drawer(string $name): OptionDrawer
    {
        foreach ($this->drawers as $drawer) {
            if ($drawer->supports($name)) {
                return $drawer;
            }
        }

        throw DrawRefused::noDrawer($name);
    }
}
