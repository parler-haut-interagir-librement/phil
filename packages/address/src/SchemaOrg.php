<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * An address as schema.org reads it, a `PostalAddress` to embed in a `Place`, an `Organization` or an `Event`: the
 * address lines joined, the locality, the region, the postal code and the country code. Who receives the mail —
 * names, organization — is not part of an address there.
 */
final class SchemaOrg
{
    /**
     * @return array<string, string>
     */
    public static function of(Address $address): array
    {
        $lines = array_filter([$address->get(AddressField::AddressLine1), $address->get(AddressField::AddressLine2), $address->get(AddressField::AddressLine3)], static fn (?string $line): bool => null !== $line);
        $locality = array_filter([$address->get(AddressField::DependentLocality), $address->get(AddressField::Locality)], static fn (?string $part): bool => null !== $part);

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => implode(', ', $lines),
            'addressLocality' => implode(', ', $locality),
            'addressRegion' => $address->get(AddressField::AdministrativeArea) ?? '',
            'postalCode' => $address->get(AddressField::PostalCode) ?? '',
            'addressCountry' => $address->country,
        ], static fn (string $value): bool => '' !== $value);
    }
}
