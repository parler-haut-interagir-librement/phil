<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;
use SensitiveParameter;

/**
 * An order asked for at a counter or a register: the code given, where, by which member of the staff, and the name of
 * the person collecting when it is not the customer.
 */
final readonly class HandOverRequest
{
    /**
     * @param string  $station   the counter or the register, as the application names it
     * @param string  $staff     the member of the staff who hands the order over
     * @param ?string $collector the name of the person collecting in the customer's place, checked by the staff
     */
    public function __construct(
        public string $order,
        #[SensitiveParameter]
        public string $code,
        public string $station,
        public string $staff,
        public DateTimeImmutable $at,
        public ?string $collector = null,
    ) {
        Check::order($order);
        Check::opaque('station', $station);
        Check::opaque('staff', $staff);

        if (null !== $collector) {
            Check::text('collector name', $collector);
        }
    }

    /**
     * The request of a scanned QR code, which carries the order number and the code.
     *
     * @throws InvalidPickup when the payload is not one of a pickup code
     */
    public static function scanned(#[SensitiveParameter] string $payload, string $station, string $staff, DateTimeImmutable $at, ?string $collector = null): self
    {
        $scanned = ScannedCode::parse($payload);

        return new self($scanned->order, $scanned->code, $station, $staff, $at, $collector);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['order' => $this->order, 'code' => '********', 'station' => $this->station, 'staff' => $this->staff, 'at' => $this->at];
    }
}
