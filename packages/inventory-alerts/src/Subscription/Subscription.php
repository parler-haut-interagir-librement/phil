<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;

/**
 * A customer's explicit request to be told once about a SKU. The address serves this alert only; the subscription
 * ends when the alert is sent, or with the one-click unsubscription.
 */
final readonly class Subscription
{
    public function __construct(
        public string $id,
        public SubscriptionKind $kind,
        public string $sku,
        public EmailAddress $email,
        public DateTimeImmutable $subscribedAt,
        public ?Price $threshold = null,
        public ?DateTimeImmutable $notifiedAt = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidInput::because('subscription identifier', 'must be 32 lowercase hexadecimal characters');
        }

        Check::code('SKU', $sku);

        if ((SubscriptionKind::PriceDrop === $kind) !== ($threshold instanceof Price)) {
            throw InvalidInput::because('price threshold', 'must be given for a price-drop alert, and only for one');
        }
    }

    public static function newId(): string
    {
        return sodium_bin2hex(random_bytes(16));
    }

    public function isActive(): bool
    {
        return !$this->notifiedAt instanceof DateTimeImmutable;
    }

    public function notified(DateTimeImmutable $at): self
    {
        return new self($this->id, $this->kind, $this->sku, $this->email, $this->subscribedAt, $this->threshold, $at);
    }
}
