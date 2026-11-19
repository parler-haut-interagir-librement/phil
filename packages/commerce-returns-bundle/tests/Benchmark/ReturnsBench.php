<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A return request checks every line asked against what the returns still holding units of the order hold, read
 * once per request from their stored form; an order returned piece by piece pays for all of
 * them at each new request.
 *
 * @BeforeMethods("setUp")
 */
final class ReturnsBench
{
    private readonly InMemoryReturns $returns;

    private readonly Returns $service;

    private readonly Order $order;

    /** @var array<string, ReturnRequest> */
    private array $holding = [];

    /** @var list<array{line: string, quantity: int, reason: ReturnReason}> */
    private array $asked = [];

    private readonly DateTimeImmutable $at;

    public function __construct()
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->returns = new InMemoryReturns();
        $this->service = new Returns(new InMemoryOrders(), $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()), ReturnPolicy::of(30, false, 100_000));
        $this->order = Order::open('A-1', Currency::of('CAD'));
    }

    /**
     * @param array{lines: int, returns: int} $params
     */
    public function setUp(array $params): void
    {
        $cad = Currency::of('CAD');

        for ($line = 0; $line < $params['lines']; ++$line) {
            $price = Money::of(1_000 + $line, $cad);
            // Room for one unit per holding return, and one more for the request measured.
            $this->order->add(Sellable::of(SellableReference::of('product', 'SKU-'.$line), 'Product '.$line, $price, 'standard'), $params['returns'] + 2, $price);
            $this->asked[] = ['line' => 'product:SKU-'.$line, 'quantity' => 1, 'reason' => ReturnReason::ChangedMind];
        }

        $this->order->place($this->at);

        // Each earlier return takes one unit of every line back and still holds them.
        for ($return = 0; $return < $params['returns']; ++$return) {
            $this->service->request($this->order, $this->asked, $this->at);
        }

        $this->holding = $this->returns->returns;
    }

    /**
     * Each rev forgets the request of the one before, so every rev sees the same holding returns.
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @ParamProviders("orders")
     */
    public function benchRequest(): void
    {
        $this->returns->returns = $this->holding;
        $this->service->request($this->order, $this->asked, $this->at);
    }

    /**
     * @return Generator<string, array{lines: int, returns: int}>
     */
    public function orders(): Generator
    {
        yield '3 lines, 1 return' => ['lines' => 3, 'returns' => 1];
        yield '10 lines, 5 returns' => ['lines' => 10, 'returns' => 5];
        yield '50 lines, 20 returns' => ['lines' => 50, 'returns' => 20];
    }
}
