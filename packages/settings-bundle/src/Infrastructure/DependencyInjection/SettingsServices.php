<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\DependencyInjection;

use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\SettingsBundle\Application\BuiltInSettings;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;
use Phil\SettingsBundle\Application\DocumentAttributes;
use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingMigration;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Infrastructure\Console\MigrateSettingsCommand;
use Phil\SettingsBundle\Infrastructure\Doctrine\DbalSettingRepository;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingSchemaListener;
use Phil\SettingsBundle\Infrastructure\Form\SettingsForm;
use Phil\SettingsBundle\Infrastructure\Http\PreferenceCookie;
use Phil\SettingsBundle\Infrastructure\Http\SettingsController;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use Phil\SettingsBundle\Infrastructure\Notifications\SettingsNotificationPreferences;
use Phil\SettingsBundle\Infrastructure\Privacy\SettingsPrivacy;
use Phil\SettingsBundle\Infrastructure\Security\SecurityCurrentUser;
use Phil\SettingsBundle\Infrastructure\Twig\SettingsExtension;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The bundle's services, from its processed configuration.
 *
 * @phpstan-type SectionConfig array{label: string, help: ?string, translation_domain: string, before: list<string>, after: list<string>}
 * @phpstan-type SettingConfig array{type: string, default: bool|int|string|list<string>|null, scope: string, section: string, label: string, help: ?string, translation_domain: string, choices: list<string>, min: ?int, max: ?int, max_length: int, pattern: ?string, role: ?string, anonymous: bool, html_attribute: ?string, before: list<string>, after: list<string>}
 * @phpstan-type Config array{store: string, connection: string, current_user: ?string, template: string, site_role: string, cookie: array{name: string, lifetime: int}, display: bool, notifications: array{enabled: bool, timezone: string}, sections: array<string, SectionConfig>, settings: array<string, SettingConfig>}
 */
final class SettingsServices
{
    /** The tag of the classes that declare settings with attributes. */
    public const string DECLARATION_TAG = 'phil_settings.declaration';

    /** The tag of the migrations of the stored values, with their `version`, `before` and `after`. */
    public const string MIGRATION_TAG = 'phil_settings.migration';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $builder, array $config): void
    {
        $builder->register(SettingCatalog::class, SettingCatalog::class)
            ->setArguments([self::sections($config), self::settings($config)])
            ->setPublic(true);

        self::store($builder, $config);

        $currentUser = $config['current_user'];

        if (null === $currentUser) {
            $builder->register(SecurityCurrentUser::class, SecurityCurrentUser::class)
                ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
            $currentUser = SecurityCurrentUser::class;
        }

        $builder->setAlias(CurrentUser::class, $currentUser)->setPublic(true);

        $builder->register(PreferenceCookieFormat::class, PreferenceCookieFormat::class)->setArguments([new Reference(SettingCatalog::class)]);
        $builder->register(PreferenceCookie::class, PreferenceCookie::class)
            ->setArguments([new Reference('request_stack'), new Reference(PreferenceCookieFormat::class), $config['cookie']['name'], $config['cookie']['lifetime']]);
        $builder->setAlias(VisitorPreferences::class, PreferenceCookie::class);

        $builder->register(Settings::class, Settings::class)
            ->setArguments([
                new Reference(SettingCatalog::class),
                new Reference(SettingRepository::class),
                new Reference(CurrentUser::class),
                new Reference(VisitorPreferences::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);

        $builder->register(DocumentAttributes::class, DocumentAttributes::class)
            ->setArguments([new Reference(SettingCatalog::class), new Reference(Settings::class)])
            ->setPublic(true);

        self::interface($builder, $config);
        self::integrations($builder, $config);
        self::migrations($builder);

        $tag = static function (ChildDefinition $definition): void {
            $definition->addTag(self::DECLARATION_TAG);
        };
        $builder->registerAttributeForAutoconfiguration(AsSetting::class, $tag);
        $builder->registerAttributeForAutoconfiguration(AsSettingSection::class, $tag);
        $builder->registerAttributeForAutoconfiguration(AsSettingMigration::class, static function (ChildDefinition $definition, AsSettingMigration $migration): void {
            $definition->addTag(self::MIGRATION_TAG, ['version' => $migration->version, 'before' => $migration->before, 'after' => $migration->after]);
        });
    }

    /**
     * A section as the container builds it.
     */
    public static function section(SectionDefinition $section): Definition
    {
        return new Definition(SectionDefinition::class, [$section->key, $section->label, $section->help, $section->translationDomain, $section->before, $section->after]);
    }

    /**
     * A setting as the container builds it.
     */
    public static function setting(SettingDefinition $setting): Definition
    {
        return new Definition(SettingDefinition::class, [
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
        ]);
    }

    /**
     * @param Config $config
     *
     * @return list<Definition>
     */
    private static function sections(array $config): array
    {
        $sections = BuiltInSettings::sections();

        foreach ($config['sections'] as $key => $section) {
            $sections[] = new SectionDefinition($key, $section['label'], $section['help'], $section['translation_domain'], $section['before'], $section['after']);
        }

        return array_map(self::section(...), $sections);
    }

    /**
     * @param Config $config
     *
     * @return list<Definition>
     */
    private static function settings(array $config): array
    {
        $settings = [
            ...$config['display'] ? BuiltInSettings::display() : [],
            ...self::notifies($config) ? BuiltInSettings::notifications() : [],
        ];

        foreach ($config['settings'] as $key => $setting) {
            $settings[] = new SettingDefinition(
                $key,
                SettingType::from($setting['type']),
                $setting['default'],
                Scope::from($setting['scope']),
                $setting['section'],
                $setting['label'],
                $setting['help'],
                $setting['translation_domain'],
                $setting['choices'],
                $setting['min'],
                $setting['max'],
                $setting['max_length'],
                $setting['pattern'],
                $setting['role'],
                $setting['anonymous'],
                $setting['html_attribute'],
                $setting['before'],
                $setting['after'],
            );
        }

        return array_map(self::setting(...), $settings);
    }

    /**
     * @param Config $config
     */
    private static function store(ContainerBuilder $builder, array $config): void
    {
        if ('memory' === $config['store']) {
            $builder->register(InMemorySettingRepository::class, InMemorySettingRepository::class);
            $builder->setAlias(SettingRepository::class, InMemorySettingRepository::class)->setPublic(true);
            $builder->setAlias(SettingMigrationStore::class, InMemorySettingRepository::class);

            return;
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $builder->register(DbalSettingRepository::class, DbalSettingRepository::class)->setArguments([$connection]);
        $builder->setAlias(SettingRepository::class, DbalSettingRepository::class)->setPublic(true);
        $builder->setAlias(SettingMigrationStore::class, DbalSettingRepository::class);
        $builder->register(SettingSchemaListener::class, SettingSchemaListener::class)
            ->setArguments([$connection])
            ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
    }

    /**
     * The migrations of the stored values and their command; `SettingMigrationsPass` gives the migrator its
     * migrations and their order.
     */
    private static function migrations(ContainerBuilder $builder): void
    {
        $builder->register(SettingMigrator::class, SettingMigrator::class)
            ->setArguments([
                new Reference(SettingCatalog::class),
                new Reference(SettingMigrationStore::class),
                new Definition(ServiceLocator::class, [[]]),
                [],
                new Reference('clock'),
            ])
            ->setPublic(true);

        $builder->register(MigrateSettingsCommand::class, MigrateSettingsCommand::class)
            ->setArguments([new Reference(SettingMigrator::class)])
            ->addTag('console.command', ['command' => 'phil:settings:migrate', 'description' => 'Applies the migrations of the stored settings not yet applied']);
    }

    /**
     * The form, the pages and the Twig functions.
     *
     * @param Config $config
     */
    private static function interface(ContainerBuilder $builder, array $config): void
    {
        $builder->register(SettingsForm::class, SettingsForm::class)
            ->setArguments([new Reference('form.factory'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);

        $builder->register(SettingsController::class, SettingsController::class)
            ->setArguments([
                new Reference(SettingCatalog::class),
                new Reference(Settings::class),
                new Reference(SettingsForm::class),
                new Reference(PreferenceCookie::class),
                new Reference('twig'),
                new Reference('router'),
                new Reference('clock'),
                new Reference(CurrentUser::class),
                $config['template'],
                $config['site_role'],
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        $builder->register(SettingsExtension::class, SettingsExtension::class)
            ->setArguments([new Reference(Settings::class), new Reference(DocumentAttributes::class)])
            ->addTag('twig.extension');
    }

    /**
     * The notification preferences, with the notifications bundle; the export and erasure, with the privacy bundle.
     *
     * @param Config $config
     */
    private static function integrations(ContainerBuilder $builder, array $config): void
    {
        if (self::notifies($config)) {
            $builder->register(SettingsNotificationPreferences::class, SettingsNotificationPreferences::class)
                ->setArguments([new Reference(Settings::class), $config['notifications']['timezone']])
                ->setPublic(true);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(SettingsPrivacy::class, SettingsPrivacy::class)
                ->setArguments([new Reference(SettingCatalog::class), new Reference(SettingRepository::class), new Reference(Settings::class)])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }

    /**
     * @param Config $config
     */
    private static function notifies(array $config): bool
    {
        return $config['notifications']['enabled'] && interface_exists(NotificationPreferences::class);
    }
}
