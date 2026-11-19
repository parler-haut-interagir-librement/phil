<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\Contract\OfflineSaleGate;
use Phil\CommercePosBundle\Domain\Exception\OfflineSaleForbidden;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * Imports the sales the main register rang up offline — the only one that sells without the network. Each becomes an order rung up in its session, **re-priced by the
 * server** — the prices, promotions and taxes of now —, and its payments are taken up to what the order comes to.
 * A difference with what the register charged is never charged nor refunded silently: it is kept on the imported
 * sale, for someone to review.
 *
 * **Importing twice changes nothing**: a sale already imported is returned as it was.
 *
 * **The sale names its own cashier and session.** An application's `OfflineSaleGate` checks that whoever sends it may
 * import it; without one, the module takes them as sent. Every payment's method is checked before anything is
 * written: cash, or a method the register records.
 */
final readonly class OfflineSales
{
    public function __construct(
        private OrderModifier $modifier,
        private OrderRepository $orders,
        private Registers $registers,
        private CashSessions $sessions,
        private RegisterCheckout $checkout,
        private RegisterPayments $registerPayments,
        private RegisterSaleRepository $sales,
        private RegisterGateway $gateway,
        private ?OfflineSaleGate $gate = null,
    ) {
    }

    /**
     * @return array{ImportedSale, bool} the imported sale, and whether it was imported now
     */
    public function import(OfflineSale $sale, DateTimeImmutable $at): array
    {
        $session = $this->sessions->get($sale->session);

        if ($this->gate instanceof OfflineSaleGate && !$this->gate->allows($sale, $session)) {
            throw OfflineSaleForbidden::sale($sale->id, $session->id);
        }

        $imported = $this->sales->findImported($sale->id);

        if ($imported instanceof ImportedSale) {
            return [$imported, false];
        }

        foreach ($sale->payments as $payment) {
            if (!$this->gateway->supports($payment['method'])) {
                throw InvalidValue::because(\sprintf('An offline sale is paid in cash or a method the register records; "%s" is neither.', $payment['method']));
            }
        }

        $register = $this->registers->get($session->register);

        if (!$register->isMain()) {
            throw PosRefused::offlineAtMainOnly($register->code);
        }

        // A sale started elsewhere — held on a phone, say — is never picked up offline: its number is taken.
        if ($this->orders->find($sale->id) instanceof Order) {
            throw PosRefused::saleNumberTaken($sale->id);
        }

        $currency = $register->currency();
        $order = Order::open($sale->id, $currency);
        $order->changeChannel($register->channel());
        $order->changeTaxIncluded($session->taxIncluded);

        foreach ($sale->lines as $line) {
            $this->modifier->add($order, SellableReference::of($line['type'], $line['id']), $line['quantity'], PricingContext::of($line['quantity'], $sale->rungAt, null, $register->channel()));
        }

        $this->checkout->tip($order, Money::of($sale->tip, $currency));
        $this->orders->add($order);
        $this->checkout->ring($order, $session, $sale->by, $sale->rungAt);

        $left = $order->total();

        foreach ($sale->payments as $payment) {
            $amount = Money::of($payment['amount'], $currency);
            $amount = $amount->isGreaterThan($left) ? $left : $amount;

            if (!$amount->isPositive()) {
                continue;
            }

            if (RegisterGateway::CASH === $payment['method']) {
                // The change was given when the sale was rung up: what was tendered is the cash due.
                $this->registerPayments->cash($order, $session, $register->rounding()->round($amount), $sale->by, $sale->rungAt, $amount);
            } else {
                $this->registerPayments->recorded($order, $session, $payment['method'], $amount, $payment['approval'], $sale->rungAt);
            }

            $left = $left->subtract($amount);
        }

        $imported = ImportedSale::of($sale->id, $session, $order->total()->subtract(Money::of($sale->total, $currency)), $sale->rungAt, $at);
        $this->sales->addImported($imported);

        return [$imported, true];
    }
}
