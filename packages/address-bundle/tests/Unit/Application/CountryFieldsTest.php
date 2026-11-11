<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Application;

use Phil\Address\AddressField;
use Phil\Address\AddressFormats;
use Phil\AddressBundle\Application\CountryFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CountryFields::class)]
final class CountryFieldsTest extends TestCase
{
    public function testACountrysFieldsAreInOrderWithTheirLabels(): void
    {
        self::assertSame([
            ['name' => 'addressLine1', 'label' => 'address.field.addressLine1', 'required' => true],
            ['name' => 'addressLine2', 'label' => 'address.field.addressLine2', 'required' => false],
            ['name' => 'addressLine3', 'label' => 'address.field.addressLine3', 'required' => false],
            ['name' => 'locality', 'label' => 'address.locality.city', 'required' => true],
            ['name' => 'administrativeArea', 'label' => 'address.administrative_area.state', 'required' => true],
            ['name' => 'postalCode', 'label' => 'address.postal_code.zip', 'required' => true],
        ], CountryFields::of('US'));
        self::assertSame(['givenName', 'familyName', 'organization'], \array_slice(array_column(CountryFields::of('US', recipient: true), 'name'), 0, 3));
        self::assertSame('address.dependent_locality.district', CountryFields::label(AddressFormats::of('KR'), AddressField::DependentLocality));
    }

    public function testACountrysSubdivisionsAreChoicesInALanguage(): void
    {
        $canada = CountryFields::subdivisions('CA', 'fr');

        self::assertContains(['code' => 'QC', 'name' => 'Québec (QC)'], $canada);
        self::assertContains(['code' => 'QC', 'name' => 'Quebec (QC)'], CountryFields::subdivisions('CA', 'en'));
        self::assertContains(['code' => 'Tokyo', 'name' => 'Tokyo'], CountryFields::subdivisions('JP'));
        self::assertSame([], CountryFields::subdivisions('FR'));
        self::assertSame([], CountryFields::subdivisions('DE'));
        self::assertCount(13, $canada);
    }
}
