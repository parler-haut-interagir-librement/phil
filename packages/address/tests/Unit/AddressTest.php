<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Unit;

use Phil\Address\Address;
use Phil\Address\AddressArray;
use Phil\Address\AddressField;
use Phil\Address\Countries;
use Phil\Address\Exception\InvalidAddress;
use Phil\Address\Exception\UnreadableAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
#[CoversClass(AddressField::class)]
#[CoversClass(AddressArray::class)]
#[CoversClass(Countries::class)]
#[CoversClass(InvalidAddress::class)]
#[CoversClass(UnreadableAddress::class)]
final class AddressTest extends TestCase
{
    public function testAnAddressKeepsItsFieldsTrimmedAndInOrder(): void
    {
        $address = Address::of(' ca ', ['postalCode' => ' H2X 3K8 ', 'addressLine1' => '1234 rue Saint-Denis', 'addressLine2' => '  ', 'locality' => null]);

        self::assertSame('CA', $address->country);
        self::assertSame(['addressLine1' => '1234 rue Saint-Denis', 'postalCode' => 'H2X 3K8'], $address->fields());
        self::assertSame(['H2X 3K8', null], [$address->get(AddressField::PostalCode), $address->get(AddressField::AddressLine2)]);
        self::assertSame('Montréal', $address->with(AddressField::Locality, 'Montréal')->get(AddressField::Locality));
        self::assertSame(['addressLine1' => '1234 rue Saint-Denis'], $address->with(AddressField::PostalCode, null)->fields());
        self::assertTrue($address->equals(Address::of('CA', ['addressLine1' => '1234 rue Saint-Denis', 'postalCode' => 'H2X 3K8'])));
        self::assertFalse($address->equals(Address::of('US', ['addressLine1' => '1234 rue Saint-Denis', 'postalCode' => 'H2X 3K8'])));
        self::assertFalse($address->equals(Address::of('CA', ['addressLine1' => '1234 rue Saint-Denis'])));
        self::assertSame(str_repeat('é', 255), Address::of('CA', ['addressLine1' => str_repeat('é', 255)])->get(AddressField::AddressLine1));
        self::assertSame([AddressField::GivenName, AddressField::AdditionalName, AddressField::FamilyName, AddressField::Organization], AddressField::recipient());
    }

    /**
     * @return iterable<string, array{string, array<string, ?string>, string}>
     */
    public static function badAddresses(): iterable
    {
        yield 'no country' => ['Canada', [], '"Canada" is not the ISO 3166-1 alpha-2 code of a country.'];
        yield 'an unknown code' => ['ZZ', [], '"ZZ" is not the ISO 3166-1 alpha-2 code of a country.'];
        yield 'an unknown field' => ['CA', ['street' => 'x'], '"street" is not a field of an address.'];
        yield 'a long field' => ['CA', ['locality' => str_repeat('a', 256)], 'The locality of an address is 255 characters or fewer.'];
        yield 'a long country' => [str_repeat('é', 150), [], '"'.str_repeat('é', 100).'" is not the ISO 3166-1 alpha-2 code of a country.'];
        yield 'a long field name' => ['CA', [str_repeat('é', 150) => 'x'], '"'.str_repeat('é', 100).'" is not a field of an address.'];
    }

    /**
     * @param array<string, ?string> $fields
     */
    #[DataProvider('badAddresses')]
    public function testWhatIsNoAddressIsRefused(string $country, array $fields, string $message): void
    {
        $this->expectException(InvalidAddress::class);
        $this->expectExceptionMessageIsOrContains($message);

        Address::of($country, $fields);
    }

    public function testCountriesHaveNames(): void
    {
        self::assertSame(['Canada', 'Allemagne', 'CA'], [Countries::name('ca', 'en'), Countries::name('DE', 'fr'), Countries::code(' ca')]);
    }

    public function testAnAddressIsAnArrayOfTexts(): void
    {
        $stored = ['country' => 'CA', 'addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal'];

        self::assertSame($stored, AddressArray::from(AddressArray::to($stored)));
        self::assertSame(['country' => 'FR'], AddressArray::from(AddressArray::to(['country' => 'FR'])));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not an array' => ['CA', 'it is not an array with a country'];
        yield 'no country' => [['locality' => 'Montréal'], 'it is not an array with a country'];
        yield 'a country that is no text' => [['country' => 1], 'it is not an array with a country'];
        yield 'a field that is no text' => [['country' => 'CA', 'locality' => 1], 'a field is not a text'];
        yield 'a field without name' => [['country' => 'CA', 0 => 'Montréal'], 'a field is not a text'];
        yield 'an unknown country' => [['country' => 'ZZ'], '"ZZ" is not the ISO 3166-1 alpha-2 code of a country'];
        yield 'an unknown field' => [['country' => 'CA', 'street' => 'x'], '"street" is not a field of an address'];
    }

    #[DataProvider('unreadable')]
    public function testWhatCannotBeReadBackIsRefused(mixed $data, string $reason): void
    {
        try {
            AddressArray::to($data);
            self::fail('An unreadable address was read.');
        } catch (UnreadableAddress $unreadableAddress) {
            self::assertSame('The stored address cannot be read: '.$reason.'.', $unreadableAddress->getMessage());
        }
    }
}
