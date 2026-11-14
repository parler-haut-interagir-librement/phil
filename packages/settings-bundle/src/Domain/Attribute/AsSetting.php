<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Attribute;

use Attribute;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingType;

/**
 * Declares a setting on a class of the application, which the container then autoconfigures: the class holds nothing
 * but its declarations, one attribute each. The arguments are those of `SettingDefinition`.
 *
 * ```php
 * #[AsSettingSection('author')]
 * #[AsSetting('author.signature', SettingType::Text, section: 'author', maxLength: 120)]
 * final class AuthorSettings {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsSetting
{
    /**
     * @param bool|int|string|list<string>|null $default
     * @param list<string>                      $choices
     * @param list<string>                      $before
     * @param list<string>                      $after
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public bool|int|string|array|null $default = null,
        public Scope $scope = Scope::User,
        public string $section = 'general',
        public string $label = '',
        public ?string $help = null,
        public string $translationDomain = 'messages',
        public array $choices = [],
        public ?int $min = null,
        public ?int $max = null,
        public int $maxLength = 255,
        public ?string $pattern = null,
        public ?string $role = null,
        public bool $anonymous = false,
        public ?string $htmlAttribute = null,
        public array $before = [],
        public array $after = [],
    ) {
    }
}
