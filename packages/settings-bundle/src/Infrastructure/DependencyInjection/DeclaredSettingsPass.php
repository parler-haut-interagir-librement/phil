<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Adds to the catalog the sections and settings the application declares with attributes, then builds the catalog
 * once, so that a mistake in any declaration — a key twice, a section missing, a circular order — stops the container
 * with its reason rather than the first page that reads a setting. It also stops it when forms are used without
 * their CSRF protection, which the bundle's pages rely on.
 */
final class DeclaredSettingsPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SettingCatalog::class)) {
            return;
        }

        if ($container->hasDefinition('form.factory') && !$container->has('form.type_extension.csrf')) {
            throw new LogicException('The settings pages protect their forms with a CSRF token: turn on framework.csrf_protection and framework.form.csrf_protection.');
        }

        $catalog = $container->getDefinition(SettingCatalog::class);
        /** @var list<Definition> $sections */
        $sections = $catalog->getArgument(0);
        /** @var list<Definition> $settings */
        $settings = $catalog->getArgument(1);

        foreach (array_keys($container->findTaggedServiceIds(SettingsServices::DECLARATION_TAG)) as $id) {
            $reflection = $container->getReflectionClass($container->getDefinition($id)->getClass());

            foreach ($reflection?->getAttributes(AsSettingSection::class) ?? [] as $attribute) {
                $section = $attribute->newInstance();
                $sections[] = SettingsServices::section(new SectionDefinition($section->key, $section->label, $section->help, $section->translationDomain, $section->before, $section->after));
            }

            foreach ($reflection?->getAttributes(AsSetting::class) ?? [] as $attribute) {
                $setting = $attribute->newInstance();
                $settings[] = SettingsServices::setting(new SettingDefinition(
                    $setting->key,
                    $setting->type,
                    $setting->default,
                    $setting->scope,
                    $setting->section,
                    $setting->label,
                    $setting->help,
                    $setting->translationDomain,
                    $setting->choices,
                    $setting->min,
                    $setting->max,
                    $setting->maxLength,
                    $setting->pattern,
                    $setting->role,
                    $setting->anonymous,
                    $setting->htmlAttribute,
                    $setting->before,
                    $setting->after,
                ));
            }
        }

        $catalog->setArguments([$sections, $settings]);

        $sectionClass = new ReflectionClass(SectionDefinition::class);
        $settingClass = new ReflectionClass(SettingDefinition::class);
        new SettingCatalog(
            array_map(static fn (Definition $section): SectionDefinition => $sectionClass->newInstanceArgs($section->getArguments()), $sections),
            array_map(static fn (Definition $setting): SettingDefinition => $settingClass->newInstanceArgs($setting->getArguments()), $settings),
        );
    }
}
