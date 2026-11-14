<?php

declare(strict_types=1);

namespace Phil\SettingsBundle;

use Override;
use Phil\SettingsBundle\Domain\Setting\Keys;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\DeclaredSettingsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingMigrationsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingsServices;
use Phil\SettingsBundle\Infrastructure\Form\SettingsForm;
use Phil\SettingsBundle\Infrastructure\Http\PreferenceCookie;
use Phil\SettingsBundle\Infrastructure\Notifications\SettingsNotificationPreferences;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The settings people change themselves, for an application:
 *
 * ```yaml
 * phil_settings:
 *     sections:
 *         author: { before: [notifications] }
 *     settings:
 *         author.signature: { type: text, section: author, max_length: 120 }
 *         site.registrations_open: { type: boolean, default: true, scope: site }
 * ```
 *
 * Packages declare theirs by prepending this configuration, the application by configuration or with
 * `#[AsSetting]` on a class. The bundle declares the display preferences — colour scheme, motion, contrast, text
 * size —, kept for visitors in a strictly necessary cookie and written on the page's root element, and, with the
 * notifications bundle, the notification preferences, which it then provides as that bundle's preferences. With the
 * privacy bundle, a person's settings are exported and erased with their account. The table is added to the Doctrine
 * schema, the form's CSRF token to the stateless ones of FrameworkBundle, and with AssetMapper the Stimulus controller
 * is served without a build step.
 */
final class PhilSettingsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string TEMPLATE = '@PhilSettings/settings.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_settings';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->enumNode('store')
                ->info('Where the values are kept: "dbal", or "memory" for tests.')
                ->values(['dbal', 'memory'])
                ->defaultValue('dbal')
            ->end()
            ->scalarNode('connection')
                ->info('The Doctrine connection of the table.')
                ->cannotBeEmpty()
                ->defaultValue('default')
            ->end()
            ->scalarNode('current_user')->info("The CurrentUser service naming whose preferences a request shows; Symfony Security's user by default.")->defaultNull()->end()
            ->scalarNode('template')->info('The template of the settings pages.')->cannotBeEmpty()->defaultValue(self::TEMPLATE)->end()
            ->scalarNode('site_role')->info("The role that changes the site's settings that name none.")->cannotBeEmpty()->defaultValue('ROLE_ADMIN')->end()
            ->arrayNode('cookie')
                ->info('The cookie of the preferences kept for visitors.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('name')
                        ->defaultValue(PreferenceCookie::NAME)
                        ->validate()->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $name))->thenInvalid('The name of the cookie is letters, digits, "_" and "-": %s.')->end()
                    ->end()
                    ->integerNode('lifetime')->info('How many seconds it lasts.')->min(60)->defaultValue(PreferenceCookie::LIFETIME)->end()
                ->end()
            ->end()
            ->booleanNode('display')->info('Whether the bundle declares the display preferences.')->defaultTrue()->end()
            ->arrayNode('notifications')
                ->info('The notification preferences, with the notifications bundle.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('timezone')->info('The time zone of the quiet hours.')->cannotBeEmpty()->defaultValue('UTC')->end()
                ->end()
            ->end();

        $this->sections($children);
        $this->settings($children);
    }

    /**
     * Adds the form's token to the stateless CSRF tokens, the controller to AssetMapper, and the bundle's preferences
     * to the notifications bundle, unless the configuration turns them off.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [SettingsForm::NAME]]]);

            if (interface_exists(AssetMapperInterface::class)) {
                $container->extension('framework', ['asset_mapper' => ['paths' => [self::CONTROLLERS_DIR => '@phil/settings-bundle']]]);
            }
        }

        if ($builder->hasExtension('phil_notifications') && $this->notifies($builder->getExtensionConfig('phil_settings'))) {
            $container->extension('phil_notifications', ['preferences' => SettingsNotificationPreferences::class]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, current_user: ?string, template: string, site_role: string, cookie: array{name: string, lifetime: int}, display: bool, notifications: array{enabled: bool, timezone: string}, sections: array<string, array{label: string, help: ?string, translation_domain: string, before: list<string>, after: list<string>}>, settings: array<string, array{type: string, default: bool|int|string|list<string>|null, scope: string, section: string, label: string, help: ?string, translation_domain: string, choices: list<string>, min: ?int, max: ?int, max_length: int, pattern: ?string, role: ?string, anonymous: bool, html_attribute: ?string, before: list<string>, after: list<string>}>} $config */
        SettingsServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DeclaredSettingsPass());
        $container->addCompilerPass(new SettingMigrationsPass());
    }

    /**
     * Whether no configuration turns the notification preferences off, read before the configuration is processed.
     *
     * @param array<array-key, mixed> $configs
     */
    private function notifies(array $configs): bool
    {
        foreach ($configs as $config) {
            $notifications = \is_array($config) ? $config['notifications'] ?? null : null;

            if (false === $notifications || (\is_array($notifications) && false === ($notifications['enabled'] ?? null))) {
                return false;
            }
        }

        return true;
    }

    private function sections(NodeBuilder $children): void
    {
        $children
            ->arrayNode('sections')
                ->info('The sections of the application, by key, such as "author"; general, notifications, privacy and display are declared.')
                ->useAttributeAsKey('key')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('label')->info('The translation key of the title; "section.<key>" when empty.')->defaultValue('')->end()
                        ->scalarNode('help')->info('The translation key of the sentence under the title.')->defaultNull()->end()
                        ->scalarNode('translation_domain')->defaultValue('messages')->end()
                        ->arrayNode('before')->info('The sections it comes before.')->scalarPrototype()->end()->end()
                        ->arrayNode('after')->info('The sections it comes after.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function settings(NodeBuilder $children): void
    {
        $children
            ->arrayNode('settings')
                ->info('The settings of the application, by key, such as "author.signature".')
                ->useAttributeAsKey('key')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->enumNode('type')->values(array_map(static fn (SettingType $type): string => $type->value, SettingType::cases()))->isRequired()->end()
                        ->variableNode('default')
                            ->info('The value until one is chosen: a boolean, an integer, a string or a list of choices.')
                            ->defaultNull()
                            ->validate()->ifTrue(static fn (mixed $value): bool => !(null === $value || \is_bool($value) || \is_int($value) || \is_string($value) || (\is_array($value) && array_is_list($value))))->thenInvalid('The default of a setting is a boolean, an integer, a string or a list: %s.')->end()
                        ->end()
                        ->enumNode('scope')->values(array_map(static fn (Scope $scope): string => $scope->value, Scope::cases()))->defaultValue(Scope::User->value)->end()
                        ->scalarNode('section')->defaultValue('general')->end()
                        ->scalarNode('label')->info('The translation key of the label; "<key>.label" when empty.')->defaultValue('')->end()
                        ->scalarNode('help')->info('The translation key of the help.')->defaultNull()->end()
                        ->scalarNode('translation_domain')->defaultValue('messages')->end()
                        ->arrayNode('choices')->info('The values of a choice, in their order.')->scalarPrototype()->end()->end()
                        ->integerNode('min')->defaultNull()->end()
                        ->integerNode('max')->defaultNull()->end()
                        ->integerNode('max_length')->info('The longest text.')->min(1)->max(SettingDefinition::TEXT_LIMIT)->defaultValue(255)->end()
                        ->scalarNode('pattern')->info('The regular expression a text matches.')->defaultNull()->end()
                        ->scalarNode('role')->info("The role that changes a site's setting; site_role when null.")->defaultNull()->end()
                        ->booleanNode('anonymous')->info('Whether a visitor keeps a boolean or a choice, in the cookie.')->defaultFalse()->end()
                        ->scalarNode('html_attribute')
                            ->info('The name after "data-" of the attribute a choice writes on the root element.')
                            ->defaultNull()
                            ->validate()->ifTrue(static fn (mixed $name): bool => null !== $name && (!\is_string($name) || 1 !== preg_match(Keys::ATTRIBUTE, $name)))->thenInvalid('The HTML attribute is the name after "data-": %s.')->end()
                        ->end()
                        ->arrayNode('before')->info('The settings of its section it comes before.')->scalarPrototype()->end()->end()
                        ->arrayNode('after')->info('The settings of its section it comes after.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
            ->end();
    }
}
