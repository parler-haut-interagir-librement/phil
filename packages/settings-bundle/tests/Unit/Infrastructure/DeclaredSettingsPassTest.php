<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\DeclaredSettingsPass;
use Phil\SettingsBundle\Infrastructure\DependencyInjection\SettingsServices;
use Phil\SettingsBundle\PhilSettingsBundle;
use Phil\SettingsBundle\Tests\Unit\Fixture\AuthorSettings;
use Phil\SettingsBundle\Tests\Unit\Fixture\TwiceDeclared;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(DeclaredSettingsPass::class)]
#[CoversClass(AsSetting::class)]
#[CoversClass(AsSettingSection::class)]
final class DeclaredSettingsPassTest extends TestCase
{
    public function testTheDeclarationsOfAttributesJoinTheCatalog(): void
    {
        $container = $this->container();
        $container->register('app.author', AuthorSettings::class)->addTag(SettingsServices::DECLARATION_TAG)->addTag(SettingsServices::DECLARATION_TAG);
        $container->register('app.nothing', stdClass::class)->addTag(SettingsServices::DECLARATION_TAG);
        $container->register('app.unknown')->addTag(SettingsServices::DECLARATION_TAG);
        $container->register('app.missing', 'App\\Missing\\Declarations')->addTag(SettingsServices::DECLARATION_TAG);
        new DeclaredSettingsPass()->process($container);

        $catalog = $this->catalog($container);

        self::assertSame(['author', 'display'], array_map(static fn (SectionDefinition $section): string => $section->key, $catalog->sections(Scope::User)));
        $section = $catalog->section('author');
        self::assertSame(['author.title', 'author.help', 'app', ['notifications'], ['general']], [$section->label, $section->help, $section->translationDomain, $section->before, $section->after]);

        self::assertSame(['author.signature', 'author.columns', 'author.mode'], array_map(static fn (SettingDefinition $setting): string => $setting->key, $catalog->settings(Scope::User, 'author')));
        $signature = $catalog->get('author.signature');
        self::assertSame(
            [SettingType::Text, 'Ada', Scope::User, 'author', 'signature', 'signature.help', 'app', [], null, null, 40, '/^\p{L}*$/u', null, false, null, ['author.columns'], []],
            [$signature->type, $signature->default, $signature->scope, $signature->section, $signature->label, $signature->help, $signature->translationDomain, $signature->choices, $signature->min, $signature->max, $signature->maxLength, $signature->pattern, $signature->role, $signature->anonymous, $signature->htmlAttribute, $signature->before, $signature->after],
        );
        self::assertSame([1, 4, ['author.signature'], 255], [$catalog->get('author.columns')->min, $catalog->get('author.columns')->max, $catalog->get('author.columns')->after, $catalog->get('author.columns')->maxLength]);
        self::assertSame([['plain', 'rich'], true, 'mode'], [$catalog->get('author.mode')->choices, $catalog->get('author.mode')->anonymous, $catalog->get('author.mode')->htmlAttribute]);
        self::assertSame([Scope::Site, 'ROLE_EDITOR'], [$catalog->get('author.open')->scope, $catalog->get('author.open')->role]);
    }

    public function testAMistakeStopsTheContainer(): void
    {
        $container = $this->container();
        $container->register('app.twice', TwiceDeclared::class)->addTag(SettingsServices::DECLARATION_TAG);

        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "display.motion" is declared twice.');

        new DeclaredSettingsPass()->process($container);
    }

    public function testAMistakeOfTheConfigurationStopsTheContainerToo(): void
    {
        $container = $this->container();
        $catalog = $container->getDefinition(SettingCatalog::class);
        /** @var list<Definition> $settings */
        $settings = $catalog->getArgument(1);
        $catalog->setArgument(1, [...$settings, new Definition(SettingDefinition::class, ['a.b', SettingType::Boolean, null, Scope::User, 'nowhere'])]);

        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('The setting "a.b" is in the section "nowhere", which is not declared.');

        new DeclaredSettingsPass()->process($container);
    }

    public function testFormsWithoutCsrfProtectionStopTheContainer(): void
    {
        $container = $this->container();
        $container->register('form.factory', stdClass::class);

        try {
            new DeclaredSettingsPass()->process($container);
            self::fail('The forms are left unprotected.');
        } catch (LogicException $logicException) {
            self::assertSame('The settings pages protect their forms with a CSRF token: turn on framework.csrf_protection and framework.form.csrf_protection.', $logicException->getMessage());
        }

        $container->register('form.type_extension.csrf', stdClass::class);
        new DeclaredSettingsPass()->process($container);
        self::assertTrue($container->hasDefinition(SettingCatalog::class));
    }

    public function testWithoutTheBundleThePassDoesNothing(): void
    {
        $container = new ContainerBuilder();
        $container->register('form.factory', stdClass::class);
        new DeclaredSettingsPass()->process($container);

        self::assertFalse($container->hasDefinition(SettingCatalog::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilSettingsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([['notifications' => false]], $container);

        return $container;
    }

    private function catalog(ContainerBuilder $container): SettingCatalog
    {
        /** @var list<Definition> $sections */
        $sections = $container->getDefinition(SettingCatalog::class)->getArgument(0);
        /** @var list<Definition> $settings */
        $settings = $container->getDefinition(SettingCatalog::class)->getArgument(1);

        return new SettingCatalog(
            array_map(static fn (Definition $section): SectionDefinition => new ReflectionClass(SectionDefinition::class)->newInstanceArgs($section->getArguments()), $sections),
            array_map(static fn (Definition $setting): SettingDefinition => new ReflectionClass(SettingDefinition::class)->newInstanceArgs($setting->getArguments()), $settings),
        );
    }
}
