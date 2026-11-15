<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;

/**
 * Charges what the options of each configured line add: one option adjustment per option that adds something,
 * labelled with the option's label, its amount the supplement times the line's quantity, its origin `option:code`.
 * The supplements are those the catalogue declares now, as prices are; it runs before the taxes, which tax them.
 */
final readonly class OptionSupplementProcessor implements OrderProcessor
{
    public function __construct(
        private LineOptions $options,
    ) {
    }

    #[Override]
    public function process(Order $order): void
    {
        $adjustments = [];

        foreach ($order->lines() as $line) {
            if ($line->configuration->isEmpty()) {
                continue;
            }

            foreach ($this->options->optionsOf($line->reference, $order->currency)->supplements($line->configuration) as [$option, $supplement]) {
                $adjustments[] = Adjustment::of(AdjustmentType::Option, $option->label, $supplement->multiply($line->quantity), $line->reference, origin: 'option:'.$option->code);
            }
        }

        $order->replaceAdjustments(AdjustmentType::Option, $adjustments);
    }
}
