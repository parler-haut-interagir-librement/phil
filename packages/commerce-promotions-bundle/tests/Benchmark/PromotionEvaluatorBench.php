<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\CommercePromotionsBundle\Tests\Unit\Support\InMemoryPromotions;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The promotion processor evaluates every active promotion against every line on each cart change, so the cost
 * grows with both the cart and the number of running promotions; each fourth promotion is a bundle of two groups, and
 * each promotion after the first is ordered after the one before.
 *
 * @BeforeMethods("setUp")
 */
final readonly class PromotionEvaluatorBench
{
    private PromotionEvaluator $evaluator;

    private Order $order;

    private DateTimeImmutable $at;

    private InMemoryPromotions $promotions;

    private Currency $currency;

    public function __construct()
    {
        $this->currency = Currency::of('CAD');
        $this->promotions = new InMemoryPromotions();
        $this->evaluator = new PromotionEvaluator($this->promotions);
        $this->order = Order::open('B-1', $this->currency);
        $this->at = new DateTimeImmutable('2026-11-28T12:00:00Z');
    }

    /**
     * @param array{lines: int, promotions: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = $this->currency;
        $promotions = $this->promotions;

        for ($p = 0; $p < $params['promotions']; ++$p) {
            $skus = [];

            for ($i = $p % $params['lines']; $i < $params['lines']; $i += $params['promotions']) {
                $skus[] = 'SKU-'.$i;
            }

            $after = 0 === $p ? [] : [\sprintf('p%03d', $p - 1)];
            $code = \sprintf('p%03d', $p);
            $promotion = match ($p % 4) {
                0 => Promotion::percentOff($code, 'Percent '.$p, 50_000, $currency),
                1 => Promotion::amountOff($code, 'Amount '.$p, Money::of(300, $currency)),
                2 => Promotion::buyNPayX($code, 'Three for two '.$p, 3, 2, $currency),
                default => Promotion::bundlePrice($code, 'Bundle '.$p, [BundleGroup::of([$skus[0]]), BundleGroup::of([] === \array_slice($skus, 1) ? ['NONE'] : \array_slice($skus, 1), 2)], Money::of(1_500, $currency)),
            };
            $promotions->add($promotion->when(skus: $skus, after: $after));
        }

        $coded = Promotion::percentOff('coded', 'Coded', 100_000, $currency)->when(requiresCode: true);
        $promotions->add($coded);
        $promotions->addCode(PromotionCode::of('SAVE10', $coded));

        for ($i = 0; $i < $params['lines']; ++$i) {
            $price = Money::of(1_000 + 10 * $i, $currency);
            $this->order->add(Sellable::of(SellableReference::of(ProductSellables::TYPE, 'SKU-'.$i), 'Item '.$i, $price, 'goods'), 1 + $i % 4, $price);
        }

        $this->order->enterCode('save10');
    }

    /**
     * @ParamProviders("carts")
     *
     * @Revs(20)
     */
    public function benchEvaluate(): void
    {
        $this->evaluator->evaluate($this->order, $this->at);
    }

    /**
     * @return Generator<string, array{lines: int, promotions: int}>
     */
    public function carts(): Generator
    {
        yield '5 lines, 3 promotions' => ['lines' => 5, 'promotions' => 3];
        yield '20 lines, 10 promotions' => ['lines' => 20, 'promotions' => 10];
        yield '100 lines, 30 promotions' => ['lines' => 100, 'promotions' => 30];
    }
}
