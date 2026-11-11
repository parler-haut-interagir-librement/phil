<?php

declare(strict_types=1);

namespace Phil\Address;

use Phil\Address\Exception\AddressException;
use Phil\Address\Exception\UnreadableAddress;

/**
 * An address as an array of texts, for one JSON column, and back: `{"country": "CA", "addressLine1": "…", …}`.
 */
final class AddressArray
{
    /**
     * @return array<string, string>
     */
    public static function from(Address $address): array
    {
        return ['country' => $address->country, ...$address->fields()];
    }

    public static function to(mixed $data): Address
    {
        if (!\is_array($data) || !\is_string($data['country'] ?? null)) {
            throw UnreadableAddress::because('it is not an array with a country');
        }

        $country = $data['country'];
        unset($data['country']);

        if (!array_all($data, static fn (mixed $value, int|string $key): bool => \is_string($key) && \is_string($value))) {
            throw UnreadableAddress::because('a field is not a text');
        }

        try {
            /** @var array<string, string> $data */
            return Address::of($country, $data);
        } catch (AddressException $addressException) {
            throw UnreadableAddress::because($addressException->getMessage());
        }
    }
}
