<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Pos;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\Text;
use Phil\Money\Money;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The lines sold at the point of sale, read through the public repositories of the point of sale and of the commerce
 * core: the cash sessions closed in the period, the sales rung in them inside the period, and the lines of each sale's
 * order.
 *
 * - the SKU is the identifier of the line's sellable, the product its description cut at 200 characters, the
 *   category the one `StockedSkus` gives the SKU;
 * - the channel is the order's, or the register's; the location is the register's; the cashier is who rang the sale;
 * - the gross amount is the line's subtotal with its options, less the taxes its prices include; the discounts are
 *   the line's promotions, and its share of the order's own promotions, split by gross amount;
 * - tips, delivery charges and fees are on no line and are left out; a cancelled order is left out.
 *
 * A sale counts once its cash session is closed, in the period it was rung in: a session still open, or closed after
 * the period, is not read.
 */
final readonly class PosSoldLines implements SoldLines
{
    public function __construct(
        private CashSessionRepository $sessions,
        private RegisterSaleRepository $sales,
        private RegisterRepository $registers,
        private OrderRepository $orders,
        private ?StockedSkus $skus = null,
    ) {
    }

    #[Override]
    public function between(Period $period): iterable
    {
        foreach ($this->sessions->closedBetween($period->start, $period->end) as $session) {
            $register = $this->registers->findRegister($session->register);

            foreach ($this->sales->salesOf($session->id) as $sale) {
                if (!$period->contains($sale->rungAt)) {
                    continue;
                }

                $order = $this->orders->find($sale->orderNumber);

                if (!$order instanceof Order || $order->cancelledAt() instanceof DateTimeImmutable) {
                    continue;
                }

                yield from $this->lines($order, $sale, $register);
            }
        }
    }

    /**
     * @return iterable<SoldLine>
     */
    private function lines(Order $order, RegisterSale $sale, ?Register $register): iterable
    {
        $options = $order->adjustmentsByLine(AdjustmentType::Option);
        $promotions = $order->adjustmentsByLine(AdjustmentType::Promotion);
        $taxes = $order->adjustmentsByLine(AdjustmentType::Tax);
        $lines = $order->lines();
        $gross = [];
        $ratios = [];

        foreach ($lines as $line) {
            $value = $line->reference->value();
            $amount = $line->subtotal()
                ->add($this->sum($line->subtotal(), $options[$value] ?? [], false))
                ->subtract($this->sum($line->subtotal(), $taxes[$value] ?? [], true));
            $gross[] = $amount;
            $ratios[] = max(0, $amount->minorUnits);
        }

        $shares = $this->orderDiscount($order, $ratios);

        foreach ($lines as $index => $line) {
            $sku = $line->reference->id;
            $discount = $this->sum($gross[$index], $promotions[$line->reference->value()] ?? [], false)->negate()->add($shares[$index] ?? Money::zero($gross[$index]->currency));

            yield new SoldLine(
                $sku,
                mb_substr(trim($line->description), 0, Text::MAX),
                $this->skus?->find($sku)?->category,
                $order->channel() ?? $register?->channel(),
                $register?->location(),
                $sale->by,
                $line->quantity,
                $gross[$index]->isNegative() ? Money::zero($gross[$index]->currency) : $gross[$index],
                $discount->isNegative() ? Money::zero($discount->currency) : $discount,
                $sale->rungAt,
            );
        }
    }

    /**
     * The order's own promotions, on no line, as a positive discount split over the lines by gross amount.
     *
     * @param list<int> $ratios one per line, in the order of the lines
     *
     * @return list<Money> one per line, in the order of the lines
     */
    private function orderDiscount(Order $order, array $ratios): array
    {
        $total = null;

        foreach ($order->adjustments(AdjustmentType::Promotion) as $adjustment) {
            if (null === $adjustment->line && !$adjustment->neutral) {
                $total = $total?->add($adjustment->amount) ?? $adjustment->amount;
            }
        }

        if (null === $total || 0 === array_sum($ratios)) {
            return [];
        }

        return $total->negate()->allocate(...$ratios);
    }

    /**
     * The adjustments' amounts, the neutral ones only or those that are not.
     *
     * @param list<Adjustment> $adjustments
     */
    private function sum(Money $zeroOf, array $adjustments, bool $neutral): Money
    {
        $sum = Money::zero($zeroOf->currency);

        foreach ($adjustments as $adjustment) {
            if ($neutral === $adjustment->neutral) {
                $sum = $sum->add($adjustment->amount);
            }
        }

        return $sum;
    }
}
