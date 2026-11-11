<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Unit;

use Phil\Address\Address;
use Phil\Address\SchemaOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
    public function testAnAddressIsAPostalAddress(): void
    {
        $address = Address::of('CA', [
            'givenName' => 'Ada',
            'organization' => 'Le Gnome Archiviste',
            'addressLine1' => '123, rue Saint-Denis',
            'addressLine2' => 'Bureau 4',
            'addressLine3' => 'Porte B',
            'dependentLocality' => 'Le Plateau',
            'locality' => 'Montréal',
            'administrativeArea' => 'QC',
            'postalCode' => 'H2X 1K4',
        ]);

        self::assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '123, rue Saint-Denis, Bureau 4, Porte B',
            'addressLocality' => 'Le Plateau, Montréal',
            'addressRegion' => 'QC',
            'postalCode' => 'H2X 1K4',
            'addressCountry' => 'CA',
        ], SchemaOrg::of($address));
        self::assertSame(['@type' => 'PostalAddress', 'streetAddress' => '1 Main St', 'addressLocality' => 'Springfield', 'addressCountry' => 'US'], SchemaOrg::of(Address::of('US', ['addressLine1' => '1 Main St', 'locality' => 'Springfield'])));
        self::assertSame(['@type' => 'PostalAddress', 'streetAddress' => 'Line 2', 'addressCountry' => 'FR'], SchemaOrg::of(Address::of('FR', ['addressLine2' => 'Line 2'])));
        self::assertSame(['@type' => 'PostalAddress', 'addressCountry' => 'FR'], SchemaOrg::of(Address::of('FR')));
    }
}
