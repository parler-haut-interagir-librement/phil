<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Where a parcel leaves from or goes to. A rate needs the country and the postal code only; a label needs the name,
 * the street, the city and, where the country has them, the province or state too.
 */
final readonly class ShippingAddress
{
    /**
     * @param list<string> $street
     */
    private function __construct(
        public string $country,
        public string $postalCode,
        public string $name,
        public array $street,
        public string $city,
        public string $region,
        public string $phone,
    ) {
    }

    /**
     * @param string       $country the ISO 3166-1 alpha-2 code, in capitals
     * @param list<string> $street  one to three lines
     * @param string       $region  the province or state, as the country's post abbreviates it
     */
    public static function of(string $country, string $postalCode, string $name = '', array $street = [], string $city = '', string $region = '', string $phone = ''): self
    {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
            throw InvalidValue::because(\sprintf('"%s" is not a country: two capital letters, as ISO 3166-1 gives them.', $country));
        }

        $postalCode = strtoupper(str_replace(' ', '', $postalCode));

        if (1 !== preg_match('/^[A-Z0-9-]{0,16}$/D', $postalCode)) {
            throw InvalidValue::because('A postal code is up to 16 letters, digits or "-".');
        }

        if (\count($street) > 3) {
            throw InvalidValue::because('An address has up to three street lines.');
        }

        foreach ([$name, $city, $region, $phone, ...$street] as $field) {
            if (mb_strlen($field) > 128 || 1 === preg_match('/[\x00-\x1F\x7F]/', $field)) {
                throw InvalidValue::because('A field of an address is up to 128 characters on one line.');
            }
        }

        return new self($country, $postalCode, trim($name), array_values(array_filter(array_map(trim(...), $street), static fn (string $line): bool => '' !== $line)), trim($city), trim($region), trim($phone));
    }

    /**
     * Whether a label can be printed to it.
     */
    public function isComplete(): bool
    {
        return '' !== $this->name && [] !== $this->street && '' !== $this->city && ('' !== $this->postalCode || !\in_array($this->country, ['CA', 'US'], true));
    }
}
