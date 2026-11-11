<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * How a country writes its addresses: the order of the fields on the lines, the format in its own script when it has
 * one, the fields it requires and those it writes in capitals, the pattern of its postal codes, what it calls its
 * subdivisions and its localities, and how deep its subdivisions go.
 */
final readonly class AddressFormat
{
    /** @var list<AddressField> the fields the format writes, read from it once */
    private array $fields;

    /** @var array<string, true> by field value */
    private array $used;

    /**
     * @param list<AddressField>    $required
     * @param list<AddressField>    $uppercase
     * @param array<string, string> $defaults  by field
     */
    public function __construct(
        public string $country,
        public string $format,
        public ?string $localFormat,
        public ?string $locale,
        public array $required,
        public array $uppercase,
        public ?string $postalCodePattern,
        public ?string $postalCodePrefix,
        public string $administrativeAreaType,
        public string $localityType,
        public string $dependentLocalityType,
        public string $postalCodeType,
        public int $subdivisionDepth,
        public array $defaults,
    ) {
        preg_match_all('/%(\w+)/', $format, $matches);
        $this->fields = array_map(AddressField::from(...), array_values(array_unique($matches[1])));
        $this->used = array_fill_keys(array_map(static fn (AddressField $field): string => $field->value, $this->fields), true);
    }

    /**
     * @param array{country: string, format: string, localFormat: ?string, locale: ?string, required: list<string>, uppercase: list<string>, postalCodePattern: ?string, postalCodePrefix: ?string, administrativeAreaType: string, localityType: string, dependentLocalityType: string, postalCodeType: string, subdivisionDepth: int, defaults: array<string, string>} $data
     */
    public static function fromArray(array $data, ?string $country = null): self
    {
        return new self(
            $country ?? $data['country'],
            $data['format'],
            $data['localFormat'],
            $data['locale'],
            array_map(AddressField::from(...), $data['required']),
            array_map(AddressField::from(...), $data['uppercase']),
            $data['postalCodePattern'],
            $data['postalCodePrefix'],
            $data['administrativeAreaType'],
            $data['localityType'],
            $data['dependentLocalityType'],
            $data['postalCodeType'],
            $data['subdivisionDepth'],
            $data['defaults'],
        );
    }

    /**
     * The fields the format writes, in the order it writes them.
     *
     * @return list<AddressField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function uses(AddressField $field): bool
    {
        return isset($this->used[$field->value]);
    }

    public function requires(AddressField $field): bool
    {
        return \in_array($field, $this->required, true);
    }

    /**
     * The fields whose value is one of the country's subdivisions, from the top.
     *
     * @return list<AddressField>
     */
    public function subdivisionFields(): array
    {
        return \array_slice([AddressField::AdministrativeArea, AddressField::Locality, AddressField::DependentLocality], 0, $this->subdivisionDepth);
    }
}
