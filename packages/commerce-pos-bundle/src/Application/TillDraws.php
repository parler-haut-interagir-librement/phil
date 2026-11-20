<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\Contract\TillDrawForm;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The draws made at the counter: once the order is paid, the register lists the options left to draw that a form
 * enters, and records what the cashier entered — real dice, rolled in front of the customer — as the draws of the
 * core. An option no form enters is left to the core's `OptionDraws`.
 */
final readonly class TillDraws
{
    /**
     * @param iterable<TillDrawForm> $forms
     */
    public function __construct(
        private Payments $payments,
        private iterable $forms = [],
    ) {
    }

    /**
     * @return list<PendingDraw> the options left to draw that a form enters, line by line
     */
    public function pending(Order $order): array
    {
        $pending = [];

        foreach ($order->lines() as $line) {
            foreach ($line->configuration->pending() as $option) {
                $value = $line->configuration->value($option);
                $drawer = $value instanceof OptionValue ? $value->drawer : null;
                $form = null === $drawer ? null : $this->form($drawer);

                if ($value instanceof OptionValue && null !== $drawer && $form instanceof TillDrawForm) {
                    $pending[] = new PendingDraw($line->reference, $line->description, $option, $value->label, $drawer, $line->quantity, $form->template($drawer));
                }
            }
        }

        return $pending;
    }

    /**
     * Records the draws of one option of a paid order's line, entered unit by unit.
     *
     * @param list<array<string, mixed>> $entered one entry per unit of the line, in order
     *
     * @return list<string> the draws recorded
     */
    public function record(Order $order, SellableReference $reference, string $option, array $entered): array
    {
        if (OrderPaymentState::Paid !== $this->payments->summary($order)->state()) {
            throw PosRefused::notPaidForDraws($order->number);
        }

        $line = $order->line($reference) ?? throw InvalidValue::because(\sprintf('The order %s has no line %s.', $order->number, $reference->value()));
        $value = $line->configuration->value($option);
        $drawer = $value instanceof OptionValue ? $value->drawer : null;

        if (!$value instanceof OptionValue || null === $drawer || !$value->isPending()) {
            throw InvalidValue::because(\sprintf('The option %s of the line %s has nothing left to draw.', $option, $reference->value()));
        }

        if (\count($entered) !== $line->quantity) {
            throw InvalidValue::because(\sprintf('The line %s takes %d draws; %d were entered.', $reference->value(), $line->quantity, \count($entered)));
        }

        $form = $this->form($drawer) ?? throw PosRefused::noDrawForm($drawer);
        $draws = [];

        foreach ($entered as $index => $entry) {
            $draws[] = $form->read($drawer, $entry, $line, $option, $index + 1);
        }

        $order->recordDraws($reference, $option, $draws);

        return $draws;
    }

    private function form(string $drawer): ?TillDrawForm
    {
        foreach ($this->forms as $form) {
            if ($form->supports($drawer)) {
                return $form;
            }
        }

        return null;
    }
}
