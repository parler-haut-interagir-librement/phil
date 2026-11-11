<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Twig;

use Locale;
use Phil\Address\Address;
use Phil\Address\AddressFormatter;
use Phil\Address\Countries;
use Phil\Address\SchemaOrg;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Addresses in templates, written as their country writes them, in the request's language unless one is given.
 */
final readonly class AddressExtension
{
    /**
     * The address on its lines, joined by line breaks — `|nl2br` shows them —; empty for `null`.
     */
    #[AsTwigFilter(name: 'address')]
    public function format(?Address $address, ?string $locale = null): string
    {
        return $address instanceof Address ? AddressFormatter::format($address, $locale ?? Locale::getDefault()) : '';
    }

    /**
     * @return list<string>
     */
    #[AsTwigFilter(name: 'address_lines')]
    public function lines(?Address $address, ?string $locale = null): array
    {
        return $address instanceof Address ? AddressFormatter::lines($address, $locale ?? Locale::getDefault()) : [];
    }

    /**
     * The address for a postal label sent from a country: its capitals, and its country's name when abroad.
     */
    #[AsTwigFilter(name: 'address_label')]
    public function label(?Address $address, string $origin, ?string $locale = null): string
    {
        return $address instanceof Address ? AddressFormatter::postalLabel($address, $origin, $locale ?? Locale::getDefault()) : '';
    }

    #[AsTwigFunction(name: 'country_name')]
    public function country(string $code, ?string $locale = null): string
    {
        return Countries::name($code, $locale ?? Locale::getDefault());
    }

    /**
     * The address as a schema.org `PostalAddress`, to embed in a `Place` or an `Organization`; `null` without one.
     *
     * @return array<string, string>|null
     */
    #[AsTwigFunction(name: 'address_schema')]
    public function schema(?Address $address): ?array
    {
        return $address instanceof Address ? SchemaOrg::of($address) : null;
    }
}
