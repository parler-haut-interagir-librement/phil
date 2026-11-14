<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Locale;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * What the anonymizers draw from for one locale: given names, family names, streets, cities, regions, postal code
 * and telephone patterns, and how an address line is written.
 *
 * **Telephone patterns are fictional ranges**, numbers the country's regulator keeps out of service for fiction
 * (555-01xx in North America, 01 99 00 in France, 07700 900 in the United Kingdom…), so that an anonymized copy
 * never rings a real person. A pack whose country reserves no such range has none, and the phone anonymizer then
 * asks for a `pattern` option rather than inventing a number that may exist.
 *
 * Patterns read `#` as a digit, `?` as an uppercase letter, `*` as either, and `\` as an escape. Street and address
 * formats name their parts in braces: `{number}`, `{street}` for a street line; `{line}`, `{city}`, `{region}`,
 * `{postal_code}`, `{country}` for an address.
 */
final readonly class LocalePack
{
    public const string CODE_PATTERN = '/^[a-z]{2,3}_[A-Z]{2}$/D';

    /** @var non-empty-list<string> */
    public array $givenNames;

    /** @var non-empty-list<string> */
    public array $familyNames;

    /** @var non-empty-list<string> */
    public array $streets;

    /** @var non-empty-list<string> */
    public array $streetFormats;

    /** @var non-empty-list<string> */
    public array $cities;

    /** @var non-empty-list<string> */
    public array $regions;

    /** @var non-empty-list<string> */
    public array $postalCodes;

    /**
     * @param list<string> $givenNames
     * @param list<string> $familyNames
     * @param list<string> $streets
     * @param list<string> $streetFormats
     * @param list<string> $cities
     * @param list<string> $regions
     * @param list<string> $postalCodes
     * @param list<string> $phones        fictional ranges only; empty when the country reserves none
     * @param string       $country       the ISO 3166-1 alpha-2 code
     */
    public function __construct(
        public string $code,
        array $givenNames,
        array $familyNames,
        array $streets,
        array $streetFormats,
        array $cities,
        array $regions,
        array $postalCodes,
        public array $phones,
        public string $country,
        public string $countryName,
        public string $addressFormat,
    ) {
        if (1 !== preg_match(self::CODE_PATTERN, $code)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a locale code such as "fr_CA".', $code));
        }

        $this->givenNames = $this->nonEmpty('given names', $givenNames);
        $this->familyNames = $this->nonEmpty('family names', $familyNames);
        $this->streets = $this->nonEmpty('streets', $streets);
        $this->streetFormats = $this->nonEmpty('street formats', $streetFormats);
        $this->cities = $this->nonEmpty('cities', $cities);
        $this->regions = $this->nonEmpty('regions', $regions);
        $this->postalCodes = $this->nonEmpty('postal codes', $postalCodes);
    }

    /**
     * @param list<string> $values
     *
     * @return non-empty-list<string>
     */
    private function nonEmpty(string $what, array $values): array
    {
        if ([] === $values) {
            throw InvalidPrivacyValue::because(\sprintf('The locale pack "%s" has no %s.', $this->code, $what));
        }

        return $values;
    }
}
