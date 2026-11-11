<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Benchmark;

use Generator;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\Address\AddressFormat;
use Phil\Address\AddressFormats;
use Phil\Address\AddressValidator;

/**
 * Every address form submit and every ValidAddress check runs the validator, which asks the format whether it uses
 * each of the twelve fields and walks the subdivision levels of the country.
 */
final class AddressValidatorBench
{
    /**
     * @var array<string, Address>
     */
    private array $addresses;

    private readonly AddressFormat $format;

    public function __construct()
    {
        $this->addresses = [
            'CA' => Address::of('CA', ['givenName' => 'Marie', 'familyName' => 'Tremblay', 'addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'Québec', 'postalCode' => 'H2X 3K8']),
            'BR' => Address::of('BR', ['givenName' => 'João', 'familyName' => 'Silva', 'addressLine1' => 'Rua das Flores, 100', 'locality' => 'Wenceslau Braz', 'administrativeArea' => 'MG', 'postalCode' => '37950-000']),
            'CN' => Address::of('CN', ['givenName' => '伟', 'familyName' => '王', 'addressLine1' => '情侣中路 1 号', 'dependentLocality' => '香洲区', 'locality' => '珠海市', 'administrativeArea' => '广东省', 'postalCode' => '519000']),
        ];
        $this->format = AddressFormats::of('CN');

        // Load the formats and the subdivision lists once, as every request after the first finds them.
        foreach ($this->addresses as $address) {
            AddressValidator::violations($address);
        }
    }

    /**
     * @ParamProviders("countries")
     *
     * @param array{country: string} $params
     */
    public function benchViolations(array $params): void
    {
        AddressValidator::violations($this->addresses[$params['country']]);
    }

    /**
     * What the validator asks the format twelve times, once per field.
     *
     * @return list<AddressField>
     */
    public function benchUsesEveryField(): array
    {
        return array_values(array_filter(AddressField::cases(), $this->format->uses(...)));
    }

    /**
     * @return Generator<string, array{country: string}>
     */
    public function countries(): Generator
    {
        yield 'CA, 1 level of 13' => ['country' => 'CA'];
        yield 'BR, 27 states then 853 cities' => ['country' => 'BR'];
        yield 'CN, 3 levels by local name' => ['country' => 'CN'];
    }
}
