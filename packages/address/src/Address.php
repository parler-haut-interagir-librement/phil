<?php

declare(strict_types=1);

namespace Phil\Address;

use Phil\Address\Exception\InvalidAddress;

/**
 * A postal address: its country, and its fields as they were given, trimmed — an empty one is none. Whether it is
 * complete and right for its country is `AddressValidator`'s to say; how it is written, `AddressFormatter`'s.
 */
final readonly class Address
{
    /**
     * @param array<string, string> $fields by `AddressField` value, in the order of `AddressField`
     */
    private function __construct(
        public string $country,
        private array $fields,
    ) {
    }

    /**
     * @param array<string, ?string> $fields by `AddressField` value
     */
    public static function of(string $country, array $fields = []): self
    {
        $known = [];

        foreach ($fields as $name => $value) {
            $field = AddressField::tryFrom($name) ?? throw InvalidAddress::because(\sprintf('"%s" is not a field of an address.', mb_substr($name, 0, 100)));
            $value = trim($value ?? '');

            if (mb_strlen($value) > 255) {
                throw InvalidAddress::because(\sprintf('The %s of an address is 255 characters or fewer.', $field->value));
            }

            if ('' !== $value) {
                $known[$field->value] = $value;
            }
        }

        $ordered = [];

        foreach (AddressField::cases() as $field) {
            if (isset($known[$field->value])) {
                $ordered[$field->value] = $known[$field->value];
            }
        }

        return new self(Countries::code($country), $ordered);
    }

    public function get(AddressField $field): ?string
    {
        return $this->fields[$field->value] ?? null;
    }

    public function with(AddressField $field, ?string $value): self
    {
        return self::of($this->country, [...$this->fields, $field->value => $value]);
    }

    /**
     * @return array<string, string> by `AddressField` value
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function equals(self $other): bool
    {
        return $this->country === $other->country && $this->fields === $other->fields;
    }
}
