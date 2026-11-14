<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application;

/**
 * The attributes the root element of a page carries for the settings that declare one: `data-<attribute>` with the
 * value of the current user or visitor, unless it is the default, which leaves the stylesheet to the system's media
 * queries.
 */
final readonly class DocumentAttributes
{
    public function __construct(
        private SettingCatalog $catalog,
        private Settings $settings,
    ) {
    }

    /**
     * @return array<string, string> the value by attribute name, `data-motion` => `reduce`
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->catalog->withHtmlAttribute() as $definition) {
            $value = $this->settings->get($definition->key);

            if (\is_string($value) && $value !== $definition->default) {
                $attributes['data-'.$definition->htmlAttribute] = $value;
            }
        }

        return $attributes;
    }
}
