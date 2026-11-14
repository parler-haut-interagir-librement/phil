<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Attribute;

use Attribute;

/**
 * Declares a section of the preferences or of the site's settings on a class of the application, beside the settings
 * it holds. The arguments are those of `SectionDefinition`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsSettingSection
{
    /**
     * @param list<string> $before
     * @param list<string> $after
     */
    public function __construct(
        public string $key,
        public string $label = '',
        public ?string $help = null,
        public string $translationDomain = 'messages',
        public array $before = [],
        public array $after = [],
    ) {
    }
}
