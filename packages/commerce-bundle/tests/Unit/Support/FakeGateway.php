<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A processor as a test sees it: it names payments `ref-1`, `ref-2`…, records the refunds asked, and reads
 * notifications written as `id|reference|event|amount`, signed with the header `x-signature: ok`.
 */
final class FakeGateway implements PaymentGateway
{
    /** @var list<array{string, int}> */
    public array $refunds = [];

    /** @var array<string, list<string>> the headers of the last notification read */
    public array $headers = [];

    private int $started = 0;

    public function __construct(
        private readonly string $method = 'card',
    ) {
    }

    #[Override]
    public function supports(string $method): bool
    {
        return $this->method === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of('ref-'.++$this->started, 'https://pay.example/'.$payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount): void
    {
        $this->refunds[] = [$payment->id, $amount->minorUnits];
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        $this->headers = $headers;

        if (['ok'] !== ($headers['x-signature'] ?? null)) {
            throw InvalidNotification::because('The signature does not verify.');
        }

        $parts = explode('|', $payload);
        $event = PaymentEvent::tryFrom($parts[2] ?? '') ?? throw InvalidNotification::because('Not an event.');

        return PaymentNotification::of($parts[0], $parts[1] ?? '', $event, isset($parts[3]) ? Money::of((int) $parts[3], Currency::of('CAD')) : null);
    }
}
