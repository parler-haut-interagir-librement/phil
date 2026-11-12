<?php

declare(strict_types=1);

namespace Phil\Contact\JSContact;

use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\Address\Exception\AddressException;

/**
 * The `addresses` of a JSContact card, as `Address`es of phil/address.
 *
 * @internal
 */
final class AddressReading
{
    /**
     * The street parts of an address, written on its lines.
     */
    private const array STREET = ['number', 'name', 'direction', 'block', 'building', 'floor', 'apartment', 'room', 'postOfficeBox', 'landmark'];

    private const array PLACES = ['district' => AddressField::DependentLocality, 'locality' => AddressField::Locality, 'region' => AddressField::AdministrativeArea];

    private const array LINES = [AddressField::AddressLine1, AddressField::AddressLine2, AddressField::AddressLine3];

    /**
     * @param array<string, mixed> $card
     *
     * @return list<Address>
     */
    public static function read(Reading $reading, array $card): array
    {
        $addresses = [];

        foreach ($reading->map($card, 'addresses', 'Address') as $id => $entry) {
            $pointer = '/addresses/'.$id;
            $country = $reading->string($entry, 'countryCode', $pointer);
            $components = $reading->list($entry, 'components', $pointer);
            $reading->unknown($entry, ['@type', 'countryCode', 'components'], $pointer);

            if (null === $country) {
                $reading->notice($pointer, 'an address without its countryCode is not kept');

                continue;
            }

            try {
                $addresses[] = Address::of($country, self::fields($reading, $components, $pointer));
            } catch (AddressException $addressException) {
                $reading->notice($pointer, $addressException->getMessage());
            }
        }

        return $addresses;
    }

    /**
     * The fields of an address from its components: the street parts on up to three lines, a `name` after another
     * starting a new one; the first `postcode` the postal code, the second the sorting code.
     *
     * @param list<mixed> $components
     *
     * @return array<string, string>
     */
    private static function fields(Reading $reading, array $components, string $pointer): array
    {
        $lines = [];
        $named = false;
        /** @var array<string, string> $fields */
        $fields = [];

        foreach ($components as $index => $entry) {
            $at = $pointer.'/components/'.$index;
            $component = $reading->object($entry, $at);
            $kind = $reading->required($component, 'kind', $at);
            $value = $reading->required($component, 'value', $at);
            $reading->unknown($component, ['kind', 'value'], $at);
            $place = self::PLACES[$kind] ?? null;

            if (\in_array($kind, self::STREET, true)) {
                if ([] === $lines || ('name' === $kind && $named)) {
                    $lines[] = $value;
                    $named = 'name' === $kind;
                } else {
                    $lines[\count($lines) - 1] .= ' '.$value;
                    $named = $named || 'name' === $kind;
                }
            } elseif (null !== $place && !isset($fields[$place->value])) {
                $fields[$place->value] = $value;
            } elseif ('postcode' === $kind && !isset($fields[AddressField::SortingCode->value])) {
                $fields[isset($fields[AddressField::PostalCode->value]) ? AddressField::SortingCode->value : AddressField::PostalCode->value] = $value;
            } else {
                $reading->notice($at, \sprintf('the address does not keep this %s component', mb_substr($kind, 0, 100)));
            }
        }

        foreach (self::LINES as $line => $field) {
            if (isset($lines[$line])) {
                $fields[$field->value] = $lines[$line];
            }
        }

        if (\count($lines) > \count(self::LINES)) {
            $reading->notice($pointer.'/components', 'an address keeps three lines');
        }

        return $fields;
    }
}
