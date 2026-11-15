<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Exception\QuoteRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Quote;
use Phil\CommerceBundle\Domain\Repository\QuoteRepository;

/**
 * Quotes: a cart recomputed one last time and offered to a customer until a date, then placed at the prices quoted
 * when accepted, or a cart again when declined.
 */
final readonly class Quotes
{
    public function __construct(
        private OrderModifier $modifier,
        private QuoteRepository $quotes,
    ) {
    }

    public function issue(Order $order, string $customer, DateTimeImmutable $at, DateTimeImmutable $validUntil): Quote
    {
        $quote = Quote::of($order, $customer, $at, $validUntil);
        $this->modifier->recalculate($order);
        $order->quote();
        $this->quotes->addQuote($quote);

        return $quote;
    }

    /**
     * Places the order as quoted: no price, promotion or tax is computed again.
     */
    public function accept(Order $order, DateTimeImmutable $at): Quote
    {
        $quote = $this->get($order);
        $quote->accept($at);

        $order->place($at);

        return $quote;
    }

    /**
     * Declines the quote; its order is a cart again, to be changed and quoted anew, or given up.
     */
    public function decline(Order $order, DateTimeImmutable $at): Quote
    {
        $quote = $this->get($order);
        $quote->decline($at);

        $order->reopen();

        return $quote;
    }

    private function get(Order $order): Quote
    {
        return $this->quotes->findQuote($order->number) ?? throw QuoteRefused::unknown($order->number);
    }
}
