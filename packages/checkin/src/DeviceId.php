<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Exception\InvalidCheckin;

/**
 * The device a scan was made on: a phone, a tablet or a kiosk of the staff. The device names itself, once, and keeps its name; the bundle's controller draws one at random.
 *
 * Letters, digits, `.`, `_`, `:` and `-`, at most 64 bytes.
 */
final readonly class DeviceId
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCheckin
     */
    public static function of(string $value): self
    {
        return new self(Identifier::check($value, 'device identifier'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
