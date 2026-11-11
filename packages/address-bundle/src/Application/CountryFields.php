<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Application;

use Phil\Address\AddressField;
use Phil\Address\AddressFormat;
use Phil\Address\AddressFormats;
use Phil\Address\Subdivision;
use Phil\Address\Subdivisions;

/**
 * The fields of a country's addresses as a form shows them: in the format's order, each with the translation key of
 * its label — `address.administrative_area.state` for the United States — and whether it is required; the states,
 * provinces or regions to choose from, named in a language.
 */
final class CountryFields
{
    /**
     * @param bool $recipient whether the fields that name the recipient are shown
     *
     * @return list<array{name: string, label: string, required: bool}>
     */
    public static function of(string $country, bool $recipient = false): array
    {
        $format = AddressFormats::of($country);
        $fields = array_filter($format->fields(), static fn (AddressField $field): bool => $recipient || !\in_array($field, AddressField::recipient(), true));

        return array_values(array_map(static fn (AddressField $field): array => [
            'name' => $field->value,
            'label' => self::label($format, $field),
            'required' => $format->requires($field),
        ], $fields));
    }

    /**
     * The first level of subdivisions, their codes and their names in a language; none when the country has none.
     *
     * @return list<array{code: string, name: string}>
     */
    public static function subdivisions(string $country, ?string $locale = null): array
    {
        return array_map(static fn (Subdivision $subdivision): array => ['code' => $subdivision->code, 'name' => self::name($subdivision, $locale)], Subdivisions::of($country));
    }

    public static function label(AddressFormat $format, AddressField $field): string
    {
        return match ($field) {
            AddressField::AdministrativeArea => 'address.administrative_area.'.$format->administrativeAreaType,
            AddressField::Locality => 'address.locality.'.$format->localityType,
            AddressField::DependentLocality => 'address.dependent_locality.'.$format->dependentLocalityType,
            AddressField::PostalCode => 'address.postal_code.'.$format->postalCodeType,
            default => 'address.field.'.$field->value,
        };
    }

    private static function name(Subdivision $subdivision, ?string $locale): string
    {
        $name = $subdivision->nameIn($locale);

        return $name === $subdivision->code ? $name : $name.' ('.$subdivision->code.')';
    }
}
