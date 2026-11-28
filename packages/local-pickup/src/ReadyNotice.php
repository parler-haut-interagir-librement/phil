<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;
use Phil\Reservation\Interval;

/**
 * What the "ready for pickup" notice tells the customer: where, in which slot, until when the order is held, and the
 * code — shown as text and as a QR code — to give at the counter. Built by the worker from the pickup it reads again.
 */
final readonly class ReadyNotice
{
    public function __construct(
        public string $order,
        public string $customer,
        public AgreedPickupPoint $point,
        public Interval $slot,
        public DateTimeImmutable $holdUntil,
        public IssuedCode $code,
        public ?string $collector = null,
    ) {
    }
}
