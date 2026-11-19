<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Benchmark;

use Generator;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\DeliveryOptions;
use Phil\CommerceLogisticsBundle\Application\ShippingProcessor;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The shipping processor runs on every recompute of a cart, and each run loads every active delivery method and
 * filters them by zone and currency in PHP; the checkout lists the same options once more.
 *
 * @BeforeMethods("setUp")
 */
final readonly class DeliveryBench
{
    private DeliveryOptions $options;

    private ShippingProcessor $processor;

    private Order $order;

    private InMemoryLogistics $methods;

    public function __construct()
    {
        $this->methods = new InMemoryLogistics();
        $this->options = new DeliveryOptions($this->methods);
        $this->processor = new ShippingProcessor($this->options);
        $this->order = Order::open('BENCH-1', Currency::of('CAD'));
    }

    /**
     * @param array{methods: int} $params
     */
    public function setUp(array $params): void
    {
        $cad = Currency::of('CAD');

        for ($index = 0; $index < $params['methods']; ++$index) {
            // A third serves other zones, a third another currency: the filter keeps about one in three.
            $currency = 1 === $index % 3 ? Currency::of('EUR') : $cad;
            $zones = 2 === $index % 3 ? ['BC', 'AB'] : ['QC', 'ON'];
            $this->methods->addMethod(DeliveryMethod::of('method-'.$index, 'Method '.$index, DeliveryKind::Shipping, Money::of(1_000 + $index, $currency), Money::of(50_000, $currency), $zones, 1, 5, priority: $index));
        }

        // The chosen method has the lowest priority: it is found last.
        $this->methods->addMethod(DeliveryMethod::of('chosen', 'Chosen', DeliveryKind::Shipping, Money::of(1_200, $cad), Money::of(7_500, $cad), ['QC'], 2, 5, priority: -1));

        for ($line = 0; $line < 5; ++$line) {
            $price = Money::of(1_500 + $line, $cad);
            $this->order->add(Sellable::of(SellableReference::of('product', 'SKU-'.$line), 'Product '.$line, $price, 'goods'), 1 + $line, $price);
        }

        $this->order->changeTaxZone('QC');
        $this->order->chooseDeliveryMethod('chosen');
    }

    /**
     * @Revs(500)
     *
     * @Iterations(5)
     *
     * @ParamProviders("methodCounts")
     */
    public function benchFor(): void
    {
        $this->options->for($this->order);
    }

    /**
     * @Revs(500)
     *
     * @Iterations(5)
     *
     * @ParamProviders("methodCounts")
     */
    public function benchProcess(): void
    {
        $this->processor->process($this->order);
    }

    /**
     * @return Generator<string, array{methods: int}>
     */
    public function methodCounts(): Generator
    {
        yield '4 methods' => ['methods' => 3];
        yield '20 methods' => ['methods' => 19];
        yield '100 methods' => ['methods' => 99];
    }
}
