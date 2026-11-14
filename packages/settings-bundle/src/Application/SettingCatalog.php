<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\UnknownSetting;
use Phil\SettingsBundle\Domain\Setting\Ordering;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;

/**
 * Every section and setting the packages and the application declared, in their order: the sections as they ask to
 * be placed, and within each section its settings as they ask.
 */
final readonly class SettingCatalog
{
    /** @var array<string, SectionDefinition> */
    private array $sections;

    /** @var array<string, SettingDefinition> section by section, each in its order */
    private array $settings;

    /** @var list<SettingDefinition> the settings written on the page's root element, in their order */
    private array $attributes;

    /**
     * @param iterable<SectionDefinition> $sections
     * @param iterable<SettingDefinition> $settings
     *
     * @throws InvalidSetting when a key is declared twice, a setting is in a section not declared, or an order is circular
     */
    public function __construct(iterable $sections, iterable $settings)
    {
        $declaredSections = [];

        foreach ($sections as $section) {
            if (isset($declaredSections[$section->key])) {
                throw InvalidSetting::because(\sprintf('The section "%s" is declared twice.', $section->key));
            }

            $declaredSections[$section->key] = $section;
        }

        $bySection = array_fill_keys(array_keys($declaredSections), []);
        $declared = [];

        foreach ($settings as $setting) {
            if (isset($declared[$setting->key])) {
                throw InvalidSetting::because(\sprintf('The setting "%s" is declared twice.', $setting->key));
            }

            if (!isset($bySection[$setting->section])) {
                throw InvalidSetting::because(\sprintf('The setting "%s" is in the section "%s", which is not declared.', $setting->key, $setting->section));
            }

            $declared[$setting->key] = $setting->section;
            $bySection[$setting->section][$setting->key] = $setting;
        }

        $this->sections = $this->ordered($declaredSections);
        $ordered = [];

        foreach (array_keys($this->sections) as $key) {
            $ordered += $this->ordered($bySection[$key]);
        }

        $this->settings = $ordered;
        $this->attributes = array_values(array_filter($ordered, static fn (SettingDefinition $setting): bool => null !== $setting->htmlAttribute));
    }

    /**
     * The settings that declare an attribute of the page's root element, in their order: read on every page, so kept
     * apart once.
     *
     * @return list<SettingDefinition>
     */
    public function withHtmlAttribute(): array
    {
        return $this->attributes;
    }

    /**
     * @throws UnknownSetting when no setting has the key
     */
    public function get(string $key): SettingDefinition
    {
        return $this->settings[$key] ?? throw UnknownSetting::named($key);
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    /**
     * @throws UnknownSetting when no section has the key
     */
    public function section(string $key): SectionDefinition
    {
        return $this->sections[$key] ?? throw UnknownSetting::section($key);
    }

    /**
     * The sections that hold at least one setting of the scope, in their order.
     *
     * @return list<SectionDefinition>
     */
    public function sections(Scope $scope): array
    {
        $used = [];

        foreach ($this->settings as $setting) {
            if ($scope === $setting->scope) {
                $used[$setting->section] = $setting->key;
            }
        }

        return array_values(array_filter($this->sections, static fn (SectionDefinition $section): bool => isset($used[$section->key])));
    }

    /**
     * The settings, of one scope and one section when they are given, in their order.
     *
     * @return list<SettingDefinition>
     */
    public function settings(?Scope $scope = null, ?string $section = null): array
    {
        return array_values(array_filter(
            $this->settings,
            static fn (SettingDefinition $setting): bool => (!$scope instanceof Scope || $scope === $setting->scope) && (null === $section || $section === $setting->section),
        ));
    }

    /**
     * @template T of SectionDefinition|SettingDefinition
     *
     * @param array<string, T> $items
     *
     * @return array<string, T>
     */
    private function ordered(array $items): array
    {
        $order = Ordering::sort(array_map(static fn (SectionDefinition|SettingDefinition $item): array => ['before' => $item->before, 'after' => $item->after], $items));

        return array_combine($order, array_map(static fn (string $key): SectionDefinition|SettingDefinition => $items[$key], $order));
    }
}
