<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit;

use Phil\PrivacyBundle\PhilPrivacyBundle;
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
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Infrastructure\Console\MigrateSettingsCommand;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\DeclaredSettingsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingMigrationsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingsServices;
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
use Phil\SettingsBundle\PhilSettingsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(PhilSettingsBundle::class)]
#[CoversClass(SettingsServices::class)]
final class PhilSettingsBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_settings', $this->extension()->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getDefinition(SettingCatalog::class)->isPublic());
        self::assertSame(DbalSettingRepository::class, (string) $container->getAlias(SettingRepository::class));
        self::assertTrue($container->getAlias(SettingRepository::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalSettingRepository::class)->getArguments());
        $listener = $container->getDefinition(SettingSchemaListener::class);
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $listener->getTag('doctrine.event_listener'));
        self::assertFalse($container->hasDefinition(InMemorySettingRepository::class));

        self::assertSame(SecurityCurrentUser::class, (string) $container->getAlias(CurrentUser::class));
        self::assertTrue($container->getAlias(CurrentUser::class)->isPublic());
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SecurityCurrentUser::class)->getArguments());

        self::assertEquals([new Reference(SettingCatalog::class)], $container->getDefinition(PreferenceCookieFormat::class)->getArguments());
        self::assertEquals([new Reference('request_stack'), new Reference(PreferenceCookieFormat::class), 'phil_preferences', 31_536_000], $container->getDefinition(PreferenceCookie::class)->getArguments());
        self::assertSame(PreferenceCookie::class, (string) $container->getAlias(VisitorPreferences::class));

        $settings = $container->getDefinition(Settings::class);
        self::assertTrue($settings->isPublic());
        self::assertSame([['method' => 'reset']], $settings->getTag('kernel.reset'));
        self::assertEquals([
            new Reference(SettingCatalog::class),
            new Reference(SettingRepository::class),
            new Reference(CurrentUser::class),
            new Reference(VisitorPreferences::class),
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $settings->getArguments());

        self::assertTrue($container->getDefinition(DocumentAttributes::class)->isPublic());
        self::assertEquals([new Reference(SettingCatalog::class), new Reference(Settings::class)], $container->getDefinition(DocumentAttributes::class)->getArguments());

        self::assertEquals([new Reference('form.factory'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SettingsForm::class)->getArguments());

        $controller = $container->getDefinition(SettingsController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals([
            new Reference(SettingCatalog::class),
            new Reference(Settings::class),
            new Reference(SettingsForm::class),
            new Reference(PreferenceCookie::class),
            new Reference('twig'),
            new Reference('router'),
            new Reference('clock'),
            new Reference(CurrentUser::class),
            '@PhilSettings/settings.html.twig',
            'ROLE_ADMIN',
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $controller->getArguments());

        $extension = $container->getDefinition(SettingsExtension::class);
        self::assertTrue($extension->hasTag('twig.extension'));
        self::assertEquals([new Reference(Settings::class), new Reference(DocumentAttributes::class)], $extension->getArguments());

        self::assertEquals([new Reference(Settings::class), 'UTC'], $container->getDefinition(SettingsNotificationPreferences::class)->getArguments());
        self::assertTrue($container->getDefinition(SettingsNotificationPreferences::class)->isPublic());

        $privacy = $container->getDefinition(SettingsPrivacy::class);
        self::assertEquals([new Reference(SettingCatalog::class), new Reference(SettingRepository::class), new Reference(Settings::class)], $privacy->getArguments());
        self::assertTrue($privacy->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertTrue($privacy->hasTag(PhilPrivacyBundle::ERASER_TAG));

        $autoconfigured = $container->getAttributeAutoconfigurators();
        foreach ([AsSetting::class, AsSettingSection::class] as $attribute) {
            $definition = new ChildDefinition('x');
            self::assertCount(1, $autoconfigured[$attribute]);
            $autoconfigured[$attribute][0]($definition);
            self::assertTrue($definition->hasTag(SettingsServices::DECLARATION_TAG), $attribute);
        }

        $migration = new ChildDefinition('x');
        self::assertCount(1, $autoconfigured[AsSettingMigration::class]);
        $autoconfigured[AsSettingMigration::class][0]($migration, new AsSettingMigration('v2', ['v3'], ['v1']));
        self::assertSame([['version' => 'v2', 'before' => ['v3'], 'after' => ['v1']]], $migration->getTag(SettingsServices::MIGRATION_TAG));

        self::assertSame(DbalSettingRepository::class, (string) $container->getAlias(SettingMigrationStore::class));
        $migrator = $container->getDefinition(SettingMigrator::class);
        self::assertTrue($migrator->isPublic());
        self::assertEquals([new Reference(SettingCatalog::class), new Reference(SettingMigrationStore::class), new Definition(ServiceLocator::class, [[]]), [], new Reference('clock')], $migrator->getArguments());
        $command = $container->getDefinition(MigrateSettingsCommand::class);
        self::assertEquals([new Reference(SettingMigrator::class)], $command->getArguments());
        self::assertSame([['command' => 'phil:settings:migrate', 'description' => 'Applies the migrations of the stored settings not yet applied']], $command->getTag('console.command'));
    }

    public function testTheBuiltInDeclarations(): void
    {
        [$sections, $settings] = $this->catalog($this->load([]));

        self::assertSame(['general', 'notifications', 'privacy', 'display'], array_map(static fn (Definition $section): mixed => $section->getArgument(0), $sections));
        self::assertSame(SectionDefinition::class, $sections[0]->getClass());
        self::assertSame(['general', 'section.general', null, 'phil_settings', [], []], $sections[0]->getArguments());
        self::assertSame(['display.theme', 'display.motion', 'display.contrast', 'display.text_size', 'notifications.digest', 'notifications.email', 'notifications.web_push', 'notifications.quiet_start', 'notifications.quiet_end'], array_map(static fn (Definition $setting): mixed => $setting->getArgument(0), $settings));
        self::assertSame(SettingDefinition::class, $settings[0]->getClass());
        self::assertSame(
            ['display.theme', SettingType::Choice, 'system', Scope::User, 'display', 'display.theme.label', null, 'phil_settings', ['system', 'light', 'dark'], null, null, 255, null, null, true, 'theme', [], []],
            $settings[0]->getArguments(),
        );

        [, $none] = $this->catalog($this->load([['display' => false, 'notifications' => ['enabled' => false]]]));
        self::assertSame([], $none);
    }

    public function testTheApplicationsDeclarations(): void
    {
        $container = $this->load([[
            'sections' => ['author' => ['label' => 'author.title', 'help' => 'author.help', 'translation_domain' => 'app', 'before' => ['notifications'], 'after' => ['general']], 'site' => []],
            'settings' => [
                'author.signature' => ['type' => 'text', 'default' => 'Ada', 'section' => 'author', 'label' => 'signature', 'help' => 'signature.help', 'translation_domain' => 'app', 'max_length' => 40, 'pattern' => '/^\p{L}*$/u', 'before' => ['author.mode']],
                'author.mode' => ['type' => 'choice', 'section' => 'author', 'choices' => ['plain', 'rich'], 'anonymous' => true, 'html_attribute' => 'mode', 'after' => ['author.signature']],
                'author.columns' => ['type' => 'integer', 'default' => 2, 'section' => 'author', 'min' => 1, 'max' => 4],
                'site.open' => ['type' => 'boolean', 'default' => true, 'scope' => 'site', 'section' => 'site', 'role' => 'ROLE_EDITOR', 'html_attribute' => null],
            ],
            'display' => false,
            'notifications' => false,
        ]]);
        [$sections, $settings] = $this->catalog($container);

        self::assertSame(['author', 'author.title', 'author.help', 'app', ['notifications'], ['general']], $sections[4]->getArguments());
        self::assertSame(['site', 'section.site', null, 'messages', [], []], $sections[5]->getArguments());
        self::assertSame(['author.signature', SettingType::Text, 'Ada', Scope::User, 'author', 'signature', 'signature.help', 'app', [], null, null, 40, '/^\p{L}*$/u', null, false, null, ['author.mode'], []], $settings[0]->getArguments());
        self::assertSame(['author.mode', SettingType::Choice, 'plain', Scope::User, 'author', 'author.mode.label', null, 'messages', ['plain', 'rich'], null, null, 255, null, null, true, 'mode', [], ['author.signature']], $settings[1]->getArguments());
        self::assertSame([1, 4, 2], [$settings[2]->getArgument(9), $settings[2]->getArgument(10), $settings[2]->getArgument(2)]);
        self::assertSame([SettingType::Boolean, true, Scope::Site, 'ROLE_EDITOR'], [$settings[3]->getArgument(1), $settings[3]->getArgument(2), $settings[3]->getArgument(3), $settings[3]->getArgument(13)]);
        self::assertFalse($container->hasDefinition(SettingsNotificationPreferences::class));
    }

    public function testTheApplicationsServices(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'connection' => 'settings',
            'current_user' => 'app.current_user',
            'template' => 'settings.html.twig',
            'site_role' => 'ROLE_OWNER',
            'cookie' => ['name' => 'prefs', 'lifetime' => 600],
            'notifications' => ['timezone' => 'America/Toronto'],
        ]]);

        self::assertSame(InMemorySettingRepository::class, (string) $container->getAlias(SettingRepository::class));
        self::assertSame(InMemorySettingRepository::class, $container->getDefinition(InMemorySettingRepository::class)->getClass());
        self::assertTrue($container->getAlias(SettingRepository::class)->isPublic());
        self::assertSame(InMemorySettingRepository::class, (string) $container->getAlias(SettingMigrationStore::class));
        self::assertFalse($container->hasDefinition(DbalSettingRepository::class));
        self::assertFalse($container->hasDefinition(SettingSchemaListener::class));
        self::assertSame('app.current_user', (string) $container->getAlias(CurrentUser::class));
        self::assertTrue($container->getAlias(CurrentUser::class)->isPublic());
        self::assertFalse($container->hasDefinition(SecurityCurrentUser::class));
        self::assertSame(['prefs', 600], \array_slice($container->getDefinition(PreferenceCookie::class)->getArguments(), 2));
        self::assertSame(['settings.html.twig', 'ROLE_OWNER'], [$container->getDefinition(SettingsController::class)->getArgument(8), $container->getDefinition(SettingsController::class)->getArgument(9)]);
        self::assertSame('America/Toronto', $container->getDefinition(SettingsNotificationPreferences::class)->getArgument(1));

        $dbal = $this->load([['store' => 'dbal', 'connection' => 'settings']]);
        self::assertEquals([new Reference('doctrine.dbal.settings_connection')], $dbal->getDefinition(DbalSettingRepository::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'settings']], $dbal->getDefinition(SettingSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testAMistakeOfTheConfigurationIsRefused(): void
    {
        foreach ([
            [['cookie' => ['name' => 'a cookie']], 'The name of the cookie is letters, digits, "_" and "-"'],
            [['cookie' => ['name' => str_repeat('a', 65)]], 'The name of the cookie is letters, digits, "_" and "-"'],
            [['cookie' => ['name' => 5]], 'The name of the cookie is letters, digits, "_" and "-"'],
            [['cookie' => ['name' => "prefs\n"]], 'The name of the cookie is letters, digits, "_" and "-"'],
            [['settings' => ['a.b' => ['type' => 'text', 'max_length' => 0]]], 'Should be greater than or equal to 1'],
            [['cookie' => ['lifetime' => 59]], 'Should be greater than or equal to 60'],
            [['settings' => ['a.b' => ['type' => 'boolean', 'default' => 1.5]]], 'The default of a setting is a boolean, an integer, a string or a list'],
            [['settings' => ['a.b' => ['type' => 'boolean', 'default' => ['a' => 'b']]]], 'The default of a setting is a boolean, an integer, a string or a list'],
            [['settings' => ['a.b' => ['type' => 'choice', 'choices' => ['x'], 'html_attribute' => 'data b']]], 'The HTML attribute is the name after "data-"'],
            [['settings' => ['a.b' => ['type' => 'choice', 'choices' => ['x'], 'html_attribute' => 5]]], 'The HTML attribute is the name after "data-"'],
            [['settings' => ['a.b' => ['type' => 'text', 'max_length' => 2001]]], 'Should be less than or equal to 2000'],
            [['settings' => ['a.b' => ['type' => 'decimal']]], 'The value "decimal" is not allowed'],
        ] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "a.b" is a choice: it offers choices.');

        $this->load([['settings' => ['a.b' => ['type' => 'choice']]]]);
    }

    public function testAKeyIsKeptAsWritten(): void
    {
        foreach ([
            [['sections' => ['my-section' => []]], '"my-section" is not the key of a section'],
            [['settings' => ['a.my-key' => ['type' => 'boolean']]], '"a.my-key" is not the key of a setting'],
        ] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidSetting $invalidSetting) {
                self::assertStringStartsWith($message, $invalidSetting->getMessage());
            }
        }
    }

    public function testTheDescriptionsOfTheContainer(): void
    {
        self::assertSame(['a', 'section.a', null, 'messages', [], []], SettingsServices::section(new SectionDefinition('a'))->getArguments());
        self::assertSame(SettingDefinition::class, SettingsServices::setting(new SettingDefinition('a.b', SettingType::Boolean))->getClass());
        self::assertSame(['a.b', SettingType::Boolean, false], \array_slice(SettingsServices::setting(new SettingDefinition('a.b', SettingType::Boolean))->getArguments(), 0, 3));
    }

    public function testExplicitNullsAndListsAreAllowed(): void
    {
        [, $settings] = $this->catalog($this->load([['display' => false, 'notifications' => false, 'settings' => [
            'a.b' => ['type' => 'boolean', 'default' => null, 'html_attribute' => null],
            'a.c' => ['type' => 'choices', 'choices' => ['x', 'y'], 'default' => ['y']],
            'a.d' => ['type' => 'text', 'default' => 'x', 'max_length' => 1],
            'a.e' => ['type' => 'integer', 'default' => 3],
            'a.f' => ['type' => 'choice', 'choices' => ['x', 'y'], 'html_attribute' => 'f-1'],
        ]]]));

        self::assertSame([false, ['y'], 'x', 3, 'f-1'], [$settings[0]->getArgument(2), $settings[1]->getArgument(2), $settings[2]->getArgument(2), $settings[3]->getArgument(2), $settings[4]->getArgument(15)]);
    }

    public function testTheBundleAddsItsTokenItsControllerAndItsPreferences(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $container->registerExtension($this->stub('framework'));
        $container->registerExtension($this->stub('phil_notifications'));

        $extension->prepend($container);

        self::assertSame([
            ['csrf_protection' => ['stateless_token_ids' => ['phil_settings']]],
            ['asset_mapper' => ['paths' => [PhilSettingsBundle::CONTROLLERS_DIR => '@phil/settings-bundle']]],
        ], $container->getExtensionConfig('framework'));
        self::assertSame([['preferences' => SettingsNotificationPreferences::class]], $container->getExtensionConfig('phil_notifications'));
        self::assertDirectoryExists(PhilSettingsBundle::CONTROLLERS_DIR);
    }

    public function testThePreferencesAreLeftToTheNotificationsBundleWhenTurnedOff(): void
    {
        foreach ([['notifications' => false], ['notifications' => ['enabled' => false]]] as $off) {
            $container = new ContainerBuilder();
            $container->registerExtension($this->stub('phil_notifications'));
            $container->prependExtensionConfig('phil_settings', ['display' => true]);
            $container->prependExtensionConfig('phil_settings', $off);
            $this->prepend($container);

            self::assertSame([], $container->getExtensionConfig('phil_notifications'));
            self::assertSame([], $container->getExtensionConfig('framework'));
        }

        $container = new ContainerBuilder();
        $container->registerExtension($this->stub('phil_notifications'));
        $container->prependExtensionConfig('phil_settings', ['notifications' => ['timezone' => 'UTC']]);
        $container->prependExtensionConfig('phil_settings', ['notifications' => true]);
        $this->prepend($container);
        self::assertSame([['preferences' => SettingsNotificationPreferences::class]], $container->getExtensionConfig('phil_notifications'));

        $alone = new ContainerBuilder();
        $this->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('phil_notifications'));
    }

    public function testTheBuildAddsThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilSettingsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof DeclaredSettingsPass));
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof SettingMigrationsPass));
    }

    /**
     * @return array{list<Definition>, list<Definition>}
     */
    private function catalog(ContainerBuilder $container): array
    {
        /** @var list<Definition> $sections */
        $sections = $container->getDefinition(SettingCatalog::class)->getArgument(0);
        /** @var list<Definition> $settings */
        $settings = $container->getDefinition(SettingCatalog::class)->getArgument(1);

        return [$sections, $settings];
    }

    private function prepend(ContainerBuilder $container): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
    }

    private function stub(string $alias): ExtensionInterface
    {
        $extension = self::createStub(ExtensionInterface::class);
        $extension->method('getAlias')->willReturn($alias);

        return $extension;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSettingsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
