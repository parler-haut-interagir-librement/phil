<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * Writes an address as its country does: the format's lines with the fields in their places, subdivisions by the code
 * or the name the country writes, empty lines and separators left over taken away.
 *
 * In the country's own language — `ja` for Japan — the local format and the local names are used. A postal label
 * writes the fields the country wants in capitals, and the country's name when the label leaves another country.
 */
final class AddressFormatter
{
    /**
     * @return list<string>
     */
    public static function lines(Address $address, ?string $locale = null): array
    {
        return self::write($address, $locale, false);
    }

    public static function format(Address $address, ?string $locale = null): string
    {
        return implode("\n", self::lines($address, $locale));
    }

    /**
     * @param string $origin the country the label is sent from
     */
    public static function postalLabel(Address $address, string $origin, ?string $locale = null): string
    {
        $lines = self::write($address, $locale, true);

        if (Countries::code($origin) !== $address->country) {
            $lines[] = mb_strtoupper(Countries::name($address->country, $locale));
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function write(Address $address, ?string $locale, bool $label): array
    {
        $format = AddressFormats::of($address->country);
        $local = null !== $format->localFormat && null !== $locale && null !== $format->locale && self::language($locale) === self::language($format->locale);
        $values = self::values($address, $format, $local ? $format->locale : $locale);
        $text = $local ? $format->localFormat : $format->format;
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $written = preg_replace_callback('/%(\w+)/', static function (array $token) use ($values, $label, $format): string {
                $field = AddressField::from($token[1]);
                $value = $values[$field->value] ?? '';

                return $label && \in_array($field, $format->uppercase, true) ? mb_strtoupper($value) : $value;
            }, $line) ?? '';
            $written = preg_replace(['/\s{2,}/u', '/^[\s,\-]+|[\s,\-]+$/u'], [' ', ''], $written) ?? '';

            if ('' !== $written && 1 !== preg_match('/^[\p{P}\p{S}\s]*$/u', $written)) {
                $lines[] = $written;
            }
        }

        return $lines;
    }

    /**
     * The values of the fields, subdivisions as the country writes them.
     *
     * @return array<string, string>
     */
    private static function values(Address $address, AddressFormat $format, ?string $locale): array
    {
        $values = $address->fields();
        $parents = [];

        foreach ($format->subdivisionFields() as $field) {
            $value = $address->get($field);
            $subdivision = null === $value ? null : Subdivisions::find($value, $address->country, ...$parents);

            if (!$subdivision instanceof Subdivision) {
                break;
            }

            $values[$field->value] = self::subdivision($subdivision, $locale);
            $parents[] = $subdivision->id;
        }

        return $values;
    }

    /**
     * A subdivision with a code of its own — `QC` — is written by it; one without, by its name.
     */
    private static function subdivision(Subdivision $subdivision, ?string $locale): string
    {
        $local = null !== $locale && null !== $subdivision->locale && self::language($locale) === self::language($subdivision->locale);

        return match (true) {
            $subdivision->code !== $subdivision->name => $local ? ($subdivision->localCode ?? $subdivision->code) : $subdivision->code,
            default => $subdivision->nameIn($locale),
        };
    }

    private static function language(string $locale): string
    {
        return strtolower(explode('_', str_replace('-', '_', $locale))[0]);
    }
}
