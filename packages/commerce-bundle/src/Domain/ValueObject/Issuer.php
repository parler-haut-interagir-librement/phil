<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Who issues a receipt, as the receipt must show it: a name, an address, and the registration number of each tax it
 * collects (`GST` → `123456789 RT0001`). A receipt keeps its issuer as it was when issued.
 */
final readonly class Issuer
{
    /**
     * @param list<string>          $address    the lines of the address, in the order they are printed
     * @param array<string, string> $taxNumbers rate code → registration number
     */
    private function __construct(
        public string $name,
        public array $address,
        public array $taxNumbers,
    ) {
    }

    /**
     * @param list<string>          $address
     * @param array<string, string> $taxNumbers
     */
    public static function of(string $name, array $address = [], array $taxNumbers = []): self
    {
        if ('' === trim($name)) {
            throw InvalidValue::because('An issuer has a name, which the receipt shows.');
        }

        foreach ([...$address, ...$taxNumbers] as $text) {
            if ('' === trim($text)) {
                throw InvalidValue::because('An address line or a registration number is never blank.');
            }
        }

        return new self($name, $address, $taxNumbers);
    }

    /**
     * @return array{name: string, address: list<string>, tax_numbers: array<string, string>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'address' => $this->address, 'tax_numbers' => $this->taxNumbers];
    }

    /**
     * @param array{name: string, address: list<string>, tax_numbers: array<string, string>} $stored
     */
    public static function fromArray(array $stored): self
    {
        return self::of($stored['name'], $stored['address'], $stored['tax_numbers']);
    }
}
