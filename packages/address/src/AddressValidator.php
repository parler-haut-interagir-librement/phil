<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * Whether an address is complete and right for its country: each field the format requires is given, no field it
 * does not use is, the postal code matches the country's pattern — and starts as its state's or province's do —,
 * and each subdivision is one of the country's, below its parent.
 */
final class AddressValidator
{
    /**
     * @return list<AddressViolation> none for a valid address
     */
    public static function violations(Address $address): array
    {
        $format = AddressFormats::of($address->country);
        $violations = [];

        foreach (AddressField::cases() as $field) {
            $given = null !== $address->get($field);

            if ($given && !$format->uses($field)) {
                $violations[] = new AddressViolation($field, Violation::Unused);
            } elseif (!$given && $format->requires($field) && $format->uses($field)) {
                $violations[] = new AddressViolation($field, Violation::Missing);
            }
        }

        [$subdivisionViolations, $postalCodePattern] = self::subdivisions($address, $format);
        $postalCode = $address->get(AddressField::PostalCode);

        if (null !== $postalCode && !self::postalCodeMatches($postalCode, $format->postalCodePattern, $postalCodePattern)) {
            $violations[] = new AddressViolation(AddressField::PostalCode, Violation::InvalidPostalCode);
        }

        return [...$violations, ...$subdivisionViolations];
    }

    public static function isValid(Address $address): bool
    {
        return [] === self::violations($address);
    }

    /**
     * @return array{list<AddressViolation>, ?string} the violations, and the postal code pattern of the first subdivision
     */
    private static function subdivisions(Address $address, AddressFormat $format): array
    {
        $parents = [];
        $pattern = null;

        foreach ($format->subdivisionFields() as $field) {
            $value = $address->get($field);
            $list = Subdivisions::of($address->country, ...$parents);

            if (null === $value || [] === $list) {
                break;
            }

            $subdivision = Subdivisions::find($value, $address->country, ...$parents);

            if (!$subdivision instanceof Subdivision) {
                return [[new AddressViolation($field, Violation::UnknownSubdivision)], $pattern];
            }

            // The data give postal code patterns to the first level of subdivisions only.
            if ([] === $parents) {
                $pattern = $subdivision->postalCodePattern;
            }

            $parents[] = $subdivision->id;
        }

        return [[], $pattern];
    }

    private static function postalCodeMatches(string $postalCode, ?string $countryPattern, ?string $subdivisionPattern): bool
    {
        return (null === $countryPattern || 1 === preg_match('/^(?:'.$countryPattern.')$/i', $postalCode))
            && (null === $subdivisionPattern || 1 === preg_match('/^(?:'.$subdivisionPattern.')/i', $postalCode));
    }
}
