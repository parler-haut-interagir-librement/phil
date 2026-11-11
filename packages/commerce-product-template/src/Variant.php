<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\Money\Money;

/**
 * One product of a design made on a template, as a plan gives it: its SKU, its language version, its combination of
 * values, its price, the channels that sell it, and the artworks it prints.
 */
final readonly class Variant
{
    /**
     * @param list<string>        $channels the channels that sell it; every channel when empty
     * @param list<PlacedArtwork> $artworks in the order of the template's faces and zones
     */
    public function __construct(
        public string $sku,
        public Design $design,
        public Template $template,
        public ?LanguageVersion $language,
        public Combination $combination,
        public Money $price,
        public array $channels,
        public array $artworks,
    ) {
    }

    /**
     * The locale its texts are read in: its language version's, else the one given.
     */
    public function locale(string $default): string
    {
        return $this->language->locale ?? $default;
    }

    /**
     * Its name, in its locale: the design, the template and the label of each value — `Gnome – Unisex shirt (M,
     * Black)`.
     */
    public function name(string $defaultLocale): string
    {
        $locale = $this->locale($defaultLocale);
        $values = array_map(static fn (AxisValue $value): string => $value->label->in($locale), $this->combination->values);

        return \sprintf('%s – %s (%s)', $this->design->name->in($locale), $this->template->name->in($locale), implode(', ', $values));
    }

    /**
     * What a catalogue keeps of it to tell it apart: the design, the template, the language version and the code of
     * each value, by axis.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['design' => $this->design->code, 'template' => $this->template->code];

        if ($this->language instanceof LanguageVersion) {
            $attributes['language'] = $this->language->code;
        }

        return $attributes + $this->combination->codes();
    }

    /**
     * The photo of the blank on that face: the first value of the combination, in the order of the axes, that has
     * one.
     */
    public function photo(string $face): ?string
    {
        foreach ($this->combination->values as $value) {
            $photo = $value->photo($face);

            if (null !== $photo) {
                return $photo;
            }
        }

        return null;
    }
}
