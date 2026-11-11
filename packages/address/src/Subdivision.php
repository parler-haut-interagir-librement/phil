<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * A state, a province, a prefecture — or a city, a district below one: its identifier among its siblings, the code an
 * address carries (`QC`, or the name where the country has no codes), its names, the language of its local names,
 * the postal codes it starts, and whether subdivisions lie below it.
 */
final readonly class Subdivision
{
    public function __construct(
        public string $id,
        public string $code,
        public ?string $localCode,
        public string $name,
        public ?string $localName,
        public ?string $isoCode,
        public ?string $postalCodePattern,
        public bool $hasChildren,
        public ?string $locale,
    ) {
    }

    /**
     * The name in a language: the local one when the language is the subdivision's, `fr` for Quebec.
     */
    public function nameIn(?string $locale): string
    {
        return null !== $this->localName && null !== $locale && null !== $this->locale && $this->language($locale) === $this->language($this->locale) ? $this->localName : $this->name;
    }

    /**
     * Whether a value of an address is this subdivision: its identifier, a code or a name, whatever the case.
     */
    public function is(string $value): bool
    {
        return \in_array(mb_strtolower(trim($value)), $this->keys(), true);
    }

    /**
     * What a value of an address may name this subdivision by, in lowercase: its identifier, its codes and its names.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(mb_strtolower(...), array_values(array_filter([$this->id, $this->code, $this->localCode, $this->name, $this->localName, $this->isoCode], \is_string(...))));
    }

    private function language(string $locale): string
    {
        return strtolower(explode('_', str_replace('-', '_', $locale))[0]);
    }
}
