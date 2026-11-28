<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeZone;
use Exception;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;

/**
 * The pickup point an order was placed for, frozen when it was placed: its name, address, stock location, clock and
 * hold period stay those the customer agreed to, whatever the point becomes afterwards. The notice and the receipt
 * show this copy.
 */
final readonly class AgreedPickupPoint
{
    public function __construct(
        public string $code,
        public string $name,
        public PickupPointKind $kind,
        public string $stockLocation,
        public string $address,
        public string $timezone,
        public int $holdHours,
    ) {
        Check::code('pickup point code', $code);
        Check::text('pickup point name', $name);
        Check::code('stock location', $stockLocation);
        Check::text('pickup point address', $address, multiline: true);
        Check::range('hold period', $holdHours, 1, PickupPoint::MAX_HOLD_HOURS);
        $this->zoneOf($timezone);
    }

    public function zone(): DateTimeZone
    {
        return $this->zoneOf($this->timezone);
    }

    /**
     * @return array{code: string, name: string, kind: string, stock_location: string, address: string, timezone: string, hold_hours: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'stock_location' => $this->stockLocation,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'hold_hours' => $this->holdHours,
        ];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave
     *
     * @throws InvalidPickup when a field is missing or out of bounds
     */
    public static function fromArray(array $data): self
    {
        $kind = PickupPointKind::tryFrom(self::string($data, 'kind'));

        if (null === $kind) {
            throw InvalidPickup::because('pickup point kind', 'is "store" or "convention_booth"');
        }

        $hold = $data['hold_hours'] ?? null;

        if (!\is_int($hold)) {
            throw InvalidPickup::because('hold period', 'is an integer');
        }

        return new self(
            self::string($data, 'code'),
            self::string($data, 'name'),
            $kind,
            self::string($data, 'stock_location'),
            self::string($data, 'address'),
            self::string($data, 'timezone'),
            $hold,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidPickup::because('field "'.$key.'" of the pickup point', 'is a string');
        }

        return $value;
    }

    private function zoneOf(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidPickup::because('time zone of the pickup point', 'is a time zone identifier');
        }
    }
}
