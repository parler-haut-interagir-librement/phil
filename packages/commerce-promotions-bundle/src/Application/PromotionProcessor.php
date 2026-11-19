<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Psr\Clock\ClockInterface;

/**
 * The core's order processor for promotions: it replaces the order's promotion adjustments with what the promotions
 * give now. It runs before the core's `TaxOrderProcessor`, so that each line is taxed once discounted.
 */
final readonly class PromotionProcessor implements OrderProcessor
{
    public function __construct(
        private PromotionEvaluator $evaluator,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function process(Order $order): void
    {
        $adjustments = [];

        foreach ($this->evaluator->evaluate($order, $this->clock->now()) as $applied) {
            array_push($adjustments, ...$applied['adjustments']);
        }

        $order->replaceAdjustments(AdjustmentType::Promotion, $adjustments);
    }
}
