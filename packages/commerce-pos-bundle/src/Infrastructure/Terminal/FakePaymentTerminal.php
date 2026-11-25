<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Infrastructure\Terminal;

use InvalidArgumentException;
use JsonException;
use Override;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommercePosBundle\Application\Contract\PaymentTerminal;
use Phil\CommercePosBundle\Application\TerminalCharge;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use SensitiveParameter;

/**
 * A payment terminal that only pretends, for tests, demos and a till's development: it remembers what it was asked —
 * until the kernel resets it, between two requests of a worker — and signs the notifications a test makes it send,
 * as a processor would, with an HMAC-SHA256 of the body under a key derived from its secret.
 *
 * Its notification is `{"id": "…", "payment": "…", "event": "captured", "amount": 1500}`, signed in the
 * `x-fake-terminal-signature` header; `amount` is read for a refund only.
 */
final class FakePaymentTerminal implements PaymentTerminal
{
    public const string NAME = 'fake';

    public const string SIGNATURE_HEADER = 'x-fake-terminal-signature';

    /** @var list<TerminalCharge> */
    private array $presented = [];

    /** @var list<string> */
    private array $cancelled = [];

    /** @var list<array{string, Money, string}> */
    private array $refunded = [];

    private int $requests = 0;

    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_commerce_pos.fake_terminal';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private readonly string $key;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw new InvalidArgumentException('The fake terminal signs with a secret of 16 bytes or more.');
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function present(TerminalCharge $charge): string
    {
        $this->presented[] = $charge;

        return \sprintf('fake_%d_%s', ++$this->requests, $charge->payment);
    }

    #[Override]
    public function cancel(string $device, string $processorReference): void
    {
        $this->cancelled[] = $processorReference;
    }

    #[Override]
    public function refund(string $processorReference, Money $amount, string $idempotencyKey): void
    {
        $this->refunded[] = [$processorReference, $amount, $idempotencyKey];
    }

    #[Override]
    public function recognises(array $headers): bool
    {
        return isset($headers[self::SIGNATURE_HEADER]);
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        $signature = $headers[self::SIGNATURE_HEADER][0] ?? '';

        if (!hash_equals($this->sign($payload), $signature)) {
            throw InvalidNotification::because("The fake terminal's signature does not verify.");
        }

        try {
            $data = json_decode($payload, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidNotification::because("The fake terminal's notification is not JSON.");
        }

        $id = \is_array($data) ? ($data['id'] ?? null) : null;
        $payment = \is_array($data) ? ($data['payment'] ?? null) : null;
        $event = \is_array($data) && \is_string($data['event'] ?? null) ? PaymentEvent::tryFrom($data['event']) : null;
        $amount = \is_array($data) ? ($data['amount'] ?? null) : null;
        $currency = \is_array($data) ? ($data['currency'] ?? null) : null;

        if (!\is_string($id) || !\is_string($payment) || !$event instanceof PaymentEvent) {
            throw InvalidNotification::because("The fake terminal's notification names an id, a payment and an event.");
        }

        $refunded = null;

        if (PaymentEvent::Refunded === $event) {
            try {
                $refunded = \is_int($amount) && \is_string($currency) ? Money::of($amount, Currency::of($currency)) : throw InvalidNotification::because("The fake terminal's refund carries its amount and currency.");
            } catch (MoneyException) {
                throw InvalidNotification::because("The fake terminal's refund carries an unknown currency.");
            }
        }

        return PaymentNotification::of($id, $payment, $event, $refunded);
    }

    /**
     * A notification as the processor would send it, body and headers, for a test to hand to the webhook.
     *
     * @return array{string, array<string, list<string>>}
     */
    public function notification(string $id, string $payment, PaymentEvent $event, ?Money $amount = null): array
    {
        $body = ['id' => $id, 'payment' => $payment, 'event' => $event->value];

        if ($amount instanceof Money) {
            $body += ['amount' => $amount->minorUnits, 'currency' => $amount->currency->code];
        }

        $payload = json_encode($body, \JSON_THROW_ON_ERROR);

        return [$payload, [self::SIGNATURE_HEADER => [$this->sign($payload)]]];
    }

    /**
     * @return list<TerminalCharge> the charges presented since the last reset, oldest first
     */
    public function presented(): array
    {
        return $this->presented;
    }

    /**
     * @return list<string> the processor references cancelled since the last reset
     */
    public function cancelled(): array
    {
        return $this->cancelled;
    }

    /**
     * @return list<array{string, Money, string}> the refunds asked since the last reset: processor reference, amount
     *                                            and idempotency key
     */
    public function refunded(): array
    {
        return $this->refunded;
    }

    /**
     * Forgets what it was asked: the kernel calls it between two requests.
     */
    public function reset(): void
    {
        $this->presented = [];
        $this->cancelled = [];
        $this->refunded = [];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->key);
    }
}
