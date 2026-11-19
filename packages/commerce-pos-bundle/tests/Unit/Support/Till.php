<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\Contract\OfflineSaleGate;
use Phil\CommercePosBundle\Application\CustomerDisplay;
use Phil\CommercePosBundle\Application\Layaways;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\RegisterGateway;
use Phil\CommercePosBundle\Application\RegisterPayments;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\SalePresets;
use Phil\CommercePosBundle\Application\TerminalAdapters;
use Phil\CommercePosBundle\Application\TerminalGateway;
use Phil\CommercePosBundle\Application\TerminalPayments;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use Phil\Money\Currency;

/**
 * The register's services over in-memory storage, a shelf of two products and no tax, a fake terminal, and the main
 * register `front`: what each test starts from.
 */
final readonly class Till
{
    public InMemoryPos $pos;

    public InMemoryOrders $orders;

    public InMemoryPayments $paymentStore;

    public OrderModifier $modifier;

    public Payments $payments;

    public Registers $registers;

    public CashSessions $sessions;

    public RegisterCheckout $checkout;

    public RegisterGateway $gateway;

    public RegisterPayments $registerPayments;

    public Layaways $layaways;

    public OfflineSales $offline;

    public FakePaymentTerminal $terminal;

    public TerminalAdapters $adapters;

    public TerminalGateway $terminalGateway;

    public TerminalPayments $terminalPayments;

    public SalePresets $presets;

    public CustomerDisplay $display;

    /**
     * @param list<OrderProcessor> $processors
     */
    public function __construct(array $processors = [], ?OfflineSaleGate $gate = null)
    {
        $this->pos = new InMemoryPos();
        $this->orders = new InMemoryOrders();
        $this->paymentStore = new InMemoryPayments();
        $shelf = new Shelf();
        $this->modifier = new OrderModifier([$shelf], [], $processors, options: new LineOptions([$shelf]));
        $this->gateway = new RegisterGateway(['debit', 'credit']);
        $this->terminal = new FakePaymentTerminal('a secret of sixteen bytes or more');
        $this->adapters = new TerminalAdapters([$this->terminal]);
        $this->terminalGateway = new TerminalGateway($this->adapters, $this->pos, $this->pos);
        $this->payments = new Payments([$this->gateway, $this->terminalGateway], $this->paymentStore, $this->orders);
        $this->registers = new Registers($this->pos);
        $this->terminalPayments = new TerminalPayments($this->payments, $this->terminalGateway, $this->adapters, $this->registers, $this->pos, $this->pos);
        $this->presets = new SalePresets($this->pos, $this->registers, $this->modifier);
        $this->display = new CustomerDisplay($this->payments);
        $this->sessions = new CashSessions($this->registers, $this->pos, $this->pos);
        $this->checkout = new RegisterCheckout($this->modifier, $this->orders, $this->registers, $this->pos, 'Pourboire');
        $this->registerPayments = new RegisterPayments($this->payments, $this->gateway, $this->pos, $this->pos, $this->pos);
        $this->layaways = new Layaways($this->payments, $this->pos, 250_000);
        $this->offline = new OfflineSales($this->modifier, $this->orders, $this->registers, $this->sessions, $this->checkout, $this->registerPayments, $this->pos, $this->gateway, $gate);
        $this->registers->create('front', 'Front counter', Currency::of('CAD'), 5, 'store-1', role: RegisterRole::Main);
    }

    /**
     * A stored cart.
     *
     * @param array<string, int> $lines product => quantity
     */
    public function cart(array $lines, string $number = 'A-1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as $product => $quantity) {
            $this->modifier->add($order, SellableReference::of('product', $product), $quantity, PricingContext::of($quantity, new DateTimeImmutable('2026-11-03 10:00:00')));
        }

        $this->orders->add($order);

        return $order;
    }
}
