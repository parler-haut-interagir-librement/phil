<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

use JsonException;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;

/**
 * What a package or the application declares of a setting: its key, its type and default value, the rules a value
 * keeps, whose it is, the section it is shown in, the translation keys of its label and help, and where it goes among
 * the settings of its section.
 *
 * A user's setting with `anonymous` is also kept for a visitor, in a cookie: only a boolean or a choice, which fit in
 * one. A choice with an `htmlAttribute` is written on the page's root element as `data-<attribute>`, unless it has its
 * default value, which leaves the page to its stylesheet's own rules: a `display.motion` of `reduce` becomes
 * `data-motion="reduce"`, its default `system` writes nothing, and the system's media queries apply.
 */
final readonly class SettingDefinition
{
    /** The longest text a setting may allow. */
    public const int TEXT_LIMIT = 2000;

    /** The most choices a setting may offer. */
    public const int CHOICE_LIMIT = 100;

    /** @var bool|int|string|list<string> */
    public bool|int|string|array $default;

    public string $label;

    /**
     * @param bool|int|string|list<string>|null $default the value until one is chosen; when null, `false`, the minimum or the integer nearest `0`, `''`, the first choice or none
     * @param string                            $label   a translation key; `<key>.label` when empty, and each choice's `<key>.choices.<choice>`
     * @param list<string>                      $choices the values of a choice, in the order they are shown
     * @param ?string                           $role    for a site's setting, the role that changes it; the bundle's `site_role` when null
     * @param list<string>                      $before  the settings of its section it comes before
     * @param list<string>                      $after   the settings of its section it comes after
     *
     * @throws InvalidSetting when the declaration contradicts itself or its default breaks its own rules
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        bool|int|string|array|null $default = null,
        public Scope $scope = Scope::User,
        public string $section = 'general',
        string $label = '',
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
        Keys::setting($key);
        Keys::section($section);
        $this->label = '' === $label ? $key.'.label' : $label;
        $this->checkChoices();
        $this->checkRules();
        $this->checkAudience();

        try {
            $this->default = $this->normalize($default ?? $this->implicitDefault());
        } catch (InvalidSettingValue $invalidSettingValue) {
            throw InvalidSetting::because(\sprintf('The default of the setting "%s" breaks its own rules: %s', $key, $invalidSettingValue->getMessage()));
        }
    }

    /**
     * The value checked against the setting's rules, as it is stored and read: a list of choices in the order of the
     * declaration, without repetition.
     *
     * @return bool|int|string|list<string>
     *
     * @throws InvalidSettingValue when the rules refuse it
     */
    public function normalize(mixed $value): bool|int|string|array
    {
        return match ($this->type) {
            SettingType::Boolean => \is_bool($value) ? $value : throw $this->refused('type', 'a boolean is expected.'),
            SettingType::Integer => $this->integer($value),
            SettingType::Text => $this->text($value),
            SettingType::Choice => \is_string($value) && \in_array($value, $this->choices, true) ? $value : throw $this->refused('choice', 'it is not one of the choices.'),
            SettingType::Choices => $this->selection($value),
        };
    }

    /**
     * @param bool|int|string|list<string> $value a value `normalize()` returned
     */
    public function encode(bool|int|string|array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * The value stored, or null when it no longer keeps the rules — a choice since removed, a range since narrowed —,
     * so that the reader falls back to the default.
     *
     * @return bool|int|string|list<string>|null
     */
    public function decode(string $stored): bool|int|string|array|null
    {
        try {
            return $this->normalize(json_decode($stored, true, flags: \JSON_THROW_ON_ERROR));
        } catch (InvalidSettingValue|JsonException) {
            return null;
        }
    }

    /**
     * @return bool|int|string|list<string>
     */
    private function implicitDefault(): bool|int|string|array
    {
        return match ($this->type) {
            SettingType::Boolean => false,
            SettingType::Integer => $this->min ?? min(0, $this->max ?? \PHP_INT_MAX),
            SettingType::Text => '',
            SettingType::Choice => $this->choices[0],
            SettingType::Choices => [],
        };
    }

    private function integer(mixed $value): int
    {
        if (!\is_int($value)) {
            throw $this->refused('type', 'an integer is expected.');
        }

        if ((null !== $this->min && $value < $this->min) || (null !== $this->max && $value > $this->max)) {
            throw $this->refused('range', \sprintf('%d is outside its range.', $value), ['%min%' => $this->min ?? \PHP_INT_MIN, '%max%' => $this->max ?? \PHP_INT_MAX]);
        }

        return $value;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw $this->refused('type', 'a string is expected.');
        }

        if (!mb_check_encoding($value, 'UTF-8') || 1 === preg_match('/\p{Cc}/u', $value)) {
            throw $this->refused('encoding', 'it is not one line of UTF-8 text.');
        }

        if (mb_strlen($value) > $this->maxLength) {
            throw $this->refused('length', \sprintf('it is longer than %d characters.', $this->maxLength), ['%max%' => $this->maxLength]);
        }

        if (null !== $this->pattern && 1 !== preg_match($this->pattern, $value)) {
            throw $this->refused('pattern', 'it does not match the pattern.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function selection(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw $this->refused('type', 'a list of choices is expected.');
        }

        foreach ($value as $choice) {
            if (!\is_string($choice) || !\in_array($choice, $this->choices, true)) {
                throw $this->refused('choice', 'one of them is not one of the choices.');
            }
        }

        return array_values(array_intersect($this->choices, $value));
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function refused(string $reason, string $message, array $parameters = []): InvalidSettingValue
    {
        return InvalidSettingValue::of($this->key, $reason, $message, $parameters);
    }

    private function checkChoices(): void
    {
        $offered = SettingType::Choice === $this->type || SettingType::Choices === $this->type;

        if ($offered !== ([] !== $this->choices)) {
            throw InvalidSetting::because(\sprintf('The setting "%s" is a %s: %s.', $this->key, $this->type->value, $offered ? 'it offers choices' : 'it offers no choices'));
        }

        if (\count($this->choices) > self::CHOICE_LIMIT || \count(array_unique($this->choices)) !== \count($this->choices)) {
            throw InvalidSetting::because(\sprintf('The choices of the setting "%s" are at most %d, each once.', $this->key, self::CHOICE_LIMIT));
        }

        foreach ($this->choices as $choice) {
            if (1 !== preg_match(Keys::CHOICE, $choice)) {
                throw InvalidSetting::because(\sprintf('"%s" is not a choice of the setting "%s": lowercase letters, digits, "_" and "-", of at most 64 characters.', $choice, $this->key));
            }
        }
    }

    private function checkRules(): void
    {
        if ((null !== $this->min || null !== $this->max) && SettingType::Integer !== $this->type) {
            throw InvalidSetting::because(\sprintf('Only an integer has a minimum and a maximum, not the setting "%s".', $this->key));
        }

        if (null !== $this->min && null !== $this->max && $this->min > $this->max) {
            throw InvalidSetting::because(\sprintf('The minimum of the setting "%s" is above its maximum.', $this->key));
        }

        if ($this->maxLength < 1 || $this->maxLength > self::TEXT_LIMIT) {
            throw InvalidSetting::because(\sprintf('The longest text of the setting "%s" is between 1 and %d characters.', $this->key, self::TEXT_LIMIT));
        }

        // A pattern that does not compile is refused; the warning of preg_match() would say nothing more.
        if (null !== $this->pattern && (SettingType::Text !== $this->type || false === @preg_match($this->pattern, ''))) {
            throw InvalidSetting::because(\sprintf('The pattern of the setting "%s" is a regular expression of a text.', $this->key));
        }
    }

    private function checkAudience(): void
    {
        $site = Scope::Site === $this->scope;

        if ($site && $this->anonymous) {
            throw InvalidSetting::because(\sprintf('The setting "%s" is the site\'s: no visitor keeps it.', $this->key));
        }

        if (!$site && null !== $this->role) {
            throw InvalidSetting::because(\sprintf('The setting "%s" is each user\'s own: no role changes it.', $this->key));
        }

        if ($this->anonymous && SettingType::Boolean !== $this->type && SettingType::Choice !== $this->type) {
            throw InvalidSetting::because(\sprintf('A visitor keeps a boolean or a choice in a cookie, not the setting "%s".', $this->key));
        }

        if (null !== $this->htmlAttribute && (SettingType::Choice !== $this->type || 1 !== preg_match(Keys::ATTRIBUTE, $this->htmlAttribute))) {
            throw InvalidSetting::because(\sprintf('The HTML attribute of the setting "%s" is the name after "data-" of a choice.', $this->key));
        }
    }
}
