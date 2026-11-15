<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Override;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Every cart change re-runs every order processor over the whole order, and every total sums lines and adjustments
 * in the order's currency, which `Order::$currency` rebuilds through `Currency::of` on each access.
 *
 * @BeforeMethods("setUp")
 */
final readonly class OrderModifierBench
{
    private OrderModifier $modifier;

    private Order $order;

    private SellableReference $reference;

    private PricingContext $context;

    private Currency $currency;

    public function __construct()
    {
        $currency = Currency::of('CAD');
        $this->currency = $currency;
        $this->modifier = new OrderModifier(
            [new readonly class($currency) implements SellableResolver {
                public function __construct(private Currency $currency)
                {
                }

                #[Override]
                public function supports(string $type): bool
                {
                    return 'product' === $type;
                }

                #[Override]
                public function resolve(SellableReference $reference): Sellable
                {
                    return Sellable::of($reference, 'Item '.$reference->id, Money::of(1_999, $this->currency), 'goods');
                }
            }],
            [],
            [new TaxOrderProcessor(new ConfiguredTaxRates(['goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]))],
        );
        $this->context = PricingContext::of(1, new DateTimeImmutable('2026-11-05 12:00:00'));
        $this->order = Order::open('B-1', $currency);
        $this->reference = SellableReference::of('product', 'p0');
    }

    /**
     * @param array{lines: int} $params
     */
    public function setUp(array $params): void
    {
        $currency = $this->currency;
        $this->order->changeTaxZone('QC');

        for ($i = 0; $i < $params['lines']; ++$i) {
            $this->order->add(Sellable::of(SellableReference::of('product', 'p'.$i), 'Item '.$i, Money::of(1_999, $currency), 'goods'), 1 + $i % 3, Money::of(1_999, $currency));
        }

        $this->modifier->recalculate($this->order);
    }

    /**
     * One unit added to an existing line: resolve, price, then every processor over the whole order.
     *
     * @ParamProviders("orders")
     *
     * @Revs(50)
     */
    public function benchAdd(): void
    {
        $this->modifier->add($this->order, $this->reference, 1, $this->context);
    }

    /**
     * @ParamProviders("orders")
     */
    public function benchTotal(): void
    {
        $this->order->total();
    }

    /**
     * @return Generator<string, array{lines: int}>
     */
    public function orders(): Generator
    {
        yield '5 lines' => ['lines' => 5];
        yield '20 lines' => ['lines' => 20];
        yield '100 lines' => ['lines' => 100];
    }
}
