<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;

/**
 * A page of the preferences or of the site's settings: its key, which is its URL, the translation keys of its title
 * and of the sentence under it, and where it goes among the others.
 */
final readonly class SectionDefinition
{
    public string $label;

    /**
     * @param string       $label  a translation key; `section.<key>` when empty
     * @param list<string> $before the sections it comes before
     * @param list<string> $after  the sections it comes after
     *
     * @throws InvalidSetting when the key is not one lowercase word
     */
    public function __construct(
        public string $key,
        string $label = '',
        public ?string $help = null,
        public string $translationDomain = 'messages',
        public array $before = [],
        public array $after = [],
    ) {
        Keys::section($key);
        $this->label = '' === $label ? 'section.'.$key : $label;
    }
}
