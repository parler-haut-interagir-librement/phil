<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit;

use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Contract\EmailLayout;
use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Contract\MarkdownRenderer;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\Contract\StyleInliner;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateEngine;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Preview\SamplePreviews;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Infrastructure\Audit\AuditTemplateChanges;
use Phil\EmailTemplatesBundle\Infrastructure\Console\CheckTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\ListTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\RenderTemplateCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Content\ContentTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplateKeysCollation;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\EmailTemplatesServices;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\EmailTemplatesBundle\Infrastructure\Filesystem\DirectoryEmbedLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Phil\EmailTemplatesBundle\Infrastructure\Markup\MarkupMarkdown;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmailHandler;
use Phil\EmailTemplatesBundle\Infrastructure\Tenancy\TenancySiteScope;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\AdminExtension;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\CssInlinerStyles;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\KitComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\NoStyleInliner;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\SandboxedEngine;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\TwigLayout;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;

#[CoversClass(PhilEmailTemplatesBundle::class)]
#[CoversClass(EmailTemplatesServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilEmailTemplatesBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getParameter('phil_email_templates.enabled'));
        self::assertSame('phil_email_templates', $this->extension()->getAlias());
        self::assertSame(ContentTemplateStore::class, (string) $container->getAlias(TemplateStore::class));
        self::assertEquals(new Reference('doctrine.orm.entity_manager'), $container->getDefinition(ContentTemplateStore::class)->getArgument(0));
        self::assertSame([['event' => 'loadClassMetadata']], $container->getDefinition(TemplateKeysCollation::class)->getTag('doctrine.event_listener'));
        self::assertSame(FixedSiteScope::class, (string) $container->getAlias(SiteScope::class));
        self::assertSame([''], $this->factoryArguments($container->getDefinition(FixedSiteScope::class)->getArgument(0)));
        self::assertSame(YamlDefaultTemplates::class, (string) $container->getAlias(DefaultTemplates::class));
        self::assertSame(['%kernel.project_dir%/email_templates', EmailTemplatesServices::BUNDLE_DEFAULTS], $container->getDefinition(YamlDefaultTemplates::class)->getArgument(0));
        self::assertDirectoryExists(EmailTemplatesServices::BUNDLE_DEFAULTS);
        self::assertSame(['en'], $this->factoryArguments($container->getDefinition(TemplateResolver::class)->getArgument(2)));
        self::assertSame(SandboxedEngine::class, (string) $container->getAlias(TemplateEngine::class));
        $engine = $container->getDefinition(SandboxedEngine::class);
        self::assertSame('%kernel.cache_dir%/phil_email_templates/twig', $engine->getArgument(2));
        self::assertEquals(new TaggedIteratorArgument(EmailTemplatesServices::SANDBOX_EXTENSION_TAG), $engine->getArgument(3));
        self::assertSame([[], [], 1_048_576], [$engine->getArgument(4), $engine->getArgument(5), $engine->getArgument(6)]);
        self::assertSame([['method' => 'reset']], $engine->getTag('kernel.reset'));
        self::assertSame(MarkupMarkdown::class, (string) $container->getAlias(MarkdownRenderer::class));
        self::assertSame(CssInlinerStyles::class, $container->getDefinition(StyleInliner::class)->getClass());
        self::assertSame(TwigLayout::class, (string) $container->getAlias(EmailLayout::class));
        self::assertSame(['@PhilEmailTemplates/email/layout.html.twig', ['name' => null, 'logo' => null]], [$container->getDefinition(TwigLayout::class)->getArgument(2), $container->getDefinition(TwigLayout::class)->getArgument(3)]);
        self::assertSame(Environment::class, $container->getDefinition(EmailTemplatesServices::LAYOUT_TWIG)->getClass());
        self::assertFalse($container->has(EmbedLoader::class));
        self::assertNull($container->getDefinition(EmailRenderer::class)->getArgument(5));
        self::assertSame(1_048_576, $container->getDefinition(EmailRenderer::class)->getArgument(6));
        self::assertNull($container->getDefinition(EmailComposer::class)->getArgument(1));
        self::assertSame(SamplePreviews::class, (string) $container->getAlias(PreviewRenderer::class));
        self::assertEquals(new Reference('clock'), $container->getDefinition(EmailTemplates::class)->getArgument(5));

        foreach ([EmailRenderer::class, EmailComposer::class, EmailTemplates::class, TemplateValidator::class, TemplateResolver::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        foreach ([ListTemplatesCommand::class, CheckTemplatesCommand::class, RenderTemplateCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        self::assertSame('en', $container->getDefinition(RenderTemplateCommand::class)->getArgument(1));

        foreach ([AdminController::class, AuditTemplateChanges::class, SendTemplatedEmailHandler::class, TenancySiteScope::class, KitComponents::class] as $absent) {
            self::assertFalse($container->has($absent), $absent);
        }
    }

    public function testEveryOptionIsWiredToItsService(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'default_locale' => 'fr-CA',
            'site' => 'main',
            'defaults_paths' => ['/app/mail'],
            'layout' => 'mail/layout.html.twig',
            'brand' => ['name' => 'Shop', 'logo' => 'https://example.com/l.png'],
            'theme' => ['light' => ['primary' => '#000'], 'dark' => ['primary' => '#fff'], 'font' => 'Georgia'],
            'embeds' => ['directory' => '/app/images', 'max_bytes' => 2048],
            'max_html_bytes' => 4096,
            'sandbox' => ['filters' => ['shout'], 'functions' => ['now'], 'cache' => false],
            'inline_css' => 'false',
            'clock' => 'my_clock',
            'preview' => 'my_preview',
            'messenger' => ['delivery_resolver' => 'my_resolver'],
            'from' => 'Shop <shop@example.com>',
        ]], ['PhilTenancyBundle' => true]);

        self::assertSame(['Shop <shop@example.com>'], $this->factoryArguments($container->getDefinition(EmailComposer::class)->getArgument(1)));

        self::assertSame(InMemoryTemplateStore::class, (string) $container->getAlias(TemplateStore::class));
        self::assertFalse($container->has(TemplateKeysCollation::class));
        self::assertEquals([new Reference('my_clock')], $container->getDefinition(InMemoryTemplateStore::class)->getArguments());
        self::assertSame(FixedSiteScope::class, (string) $container->getAlias(SiteScope::class), 'A fixed site wins over the tenancy.');
        self::assertSame(['main'], $this->factoryArguments($container->getDefinition(FixedSiteScope::class)->getArgument(0)));
        self::assertSame(['fr-CA'], $this->factoryArguments($container->getDefinition(TemplateResolver::class)->getArgument(2)));
        self::assertSame(['/app/mail', EmailTemplatesServices::BUNDLE_DEFAULTS], $container->getDefinition(YamlDefaultTemplates::class)->getArgument(0));
        self::assertSame(['mail/layout.html.twig', ['name' => 'Shop', 'logo' => 'https://example.com/l.png']], [$container->getDefinition(TwigLayout::class)->getArgument(2), $container->getDefinition(TwigLayout::class)->getArgument(3)]);
        self::assertSame([['primary' => '#000'], ['primary' => '#fff'], 'Georgia'], $container->getDefinition(EmailTemplatesServices::THEME)->getArguments());
        self::assertSame(['/app/images', 2048], $container->getDefinition(DirectoryEmbedLoader::class)->getArguments());
        self::assertEquals(new Reference(EmbedLoader::class), $container->getDefinition(EmailRenderer::class)->getArgument(5));
        self::assertSame(4096, $container->getDefinition(EmailRenderer::class)->getArgument(6));
        $engine = $container->getDefinition(SandboxedEngine::class);
        self::assertSame([false, ['shout'], ['now'], 4096], [$engine->getArgument(2), $engine->getArgument(4), $engine->getArgument(5), $engine->getArgument(6)]);
        self::assertSame(NoStyleInliner::class, $container->getDefinition(StyleInliner::class)->getClass());
        self::assertEquals(new Reference('my_clock'), $container->getDefinition(EmailTemplates::class)->getArgument(5));
        self::assertSame('my_preview', (string) $container->getAlias(PreviewRenderer::class));
        self::assertFalse($container->has(SamplePreviews::class));
        self::assertSame('my_resolver', (string) $container->getAlias(DeliveryResolver::class));
        self::assertTrue($container->getDefinition(SendTemplatedEmailHandler::class)->hasTag('messenger.message_handler'));
    }

    public function testAStoreOfTheApplicationAndTheEntityManager(): void
    {
        self::assertSame('app.store', (string) $this->load([['store' => 'app.store']])->getAlias(TemplateStore::class));
        self::assertFalse($this->load([['store' => 'app.store']])->has(ContentTemplateStore::class));
        $container = $this->load([['entity_manager' => 'doctrine.orm.mail_entity_manager']]);
        self::assertEquals(new Reference('doctrine.orm.mail_entity_manager'), $container->getDefinition(ContentTemplateStore::class)->getArgument(0));
    }

    public function testTheSiteIsTheTenantWhenTheTenancyBundleIsEnabled(): void
    {
        self::assertSame(TenancySiteScope::class, (string) $this->load([], ['PhilTenancyBundle' => true])->getAlias(SiteScope::class));
        self::assertSame(TenancySiteScope::class, (string) $this->load([['site_scope' => 'tenancy']])->getAlias(SiteScope::class));
        self::assertSame(FixedSiteScope::class, (string) $this->load([])->getAlias(SiteScope::class));
        self::assertSame(FixedSiteScope::class, (string) $this->load([['site' => 'main']], ['PhilTenancyBundle' => true])->getAlias(SiteScope::class), 'A site named in the configuration wins.');
    }

    public function testTheAuditWhenTheAuditBundleIsEnabled(): void
    {
        $audit = $this->load([['audit' => ['segment' => 'mails']]], ['PhilAuditBundle' => true])->getDefinition(AuditTemplateChanges::class);

        self::assertSame('mails', $audit->getArgument(1));
        self::assertCount(4, $audit->getTag('kernel.event_listener'));
        self::assertFalse($this->load([['audit' => ['enabled' => false]]], ['PhilAuditBundle' => true])->has(AuditTemplateChanges::class));
    }

    public function testTheAdministration(): void
    {
        $container = $this->load([['admin' => ['enabled' => true, 'role' => 'ROLE_MAIL', 'layout' => 'admin.html.twig'], 'kit_subdirectory' => 'ui']]);
        $controller = $container->getDefinition(AdminController::class);

        self::assertSame(['ROLE_MAIL', 'admin.html.twig'], [$controller->getArgument(8), $controller->getArgument(9)]);
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(AdminExtension::class)->hasTag('twig.extension'));
    }

    public function testTheLayoutUsesTheTwigOfTheApplication(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        });
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => true, 'PhilContentBundle' => true]);
        $this->extension()->load([], $container);

        self::assertSame('twig', (string) $container->getAlias(EmailTemplatesServices::LAYOUT_TWIG));
    }

    public function testDisabled(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->getParameter('phil_email_templates.enabled'));
        self::assertFalse($container->has(EmailRenderer::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        yield 'locale' => [['default_locale' => 'French'], 'The default locale is a language'];
        yield 'site' => [['site' => 'a b'], 'A site is 1 to 64 letters'];
        yield 'logo' => [['brand' => ['logo' => 'http://example.com/l.png']], 'The logo is an https address'];
        yield 'scope' => [['site_scope' => 'host'], 'The value "host" is not allowed'];
        yield 'small ceiling' => [['max_html_bytes' => 10], 'Should be greater than or equal to 1024'];
        yield 'inline' => [['inline_css' => 'yes'], 'The value "yes" is not allowed'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedConfigurations')]
    public function testARefusedConfiguration(array $config, string $error): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($error);

        $this->load([$config]);
    }

    public function testTheCsrfTokenIsStatelessAndTheCompilerPasses(): void
    {
        $bundle = new PhilEmailTemplatesBundle();
        $container = new ContainerBuilder();
        $bundle->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(CsrfProtectionPass::class, $passes);
        self::assertContains(KitSubdirectoryPass::class, $passes);
    }

    public function testTheAdministrationNeedsTheCsrfProtection(): void
    {
        $container = new ContainerBuilder();
        new CsrfProtectionPass()->process($container);
        $container->register(AdminController::class);
        $container->register(CsrfProtectionPass::TOKEN_MANAGER);
        new CsrfProtectionPass()->process($container);
        $container->removeDefinition(CsrfProtectionPass::TOKEN_MANAGER);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');
        new CsrfProtectionPass()->process($container);
    }

    public function testTheRecipesAreNamedAfterTheToolkit(): void
    {
        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);
        self::assertFalse($container->has(KitComponents::class));

        $container->setDefinition(KitComponents::class, new Definition(KitComponents::class, [null]));
        new KitSubdirectoryPass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->setDefinition(KitComponents::class, new Definition(KitComponents::class, [null]));
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui/kit', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->setDefinition(KitComponents::class, new Definition(KitComponents::class, ['mine']));
        new KitSubdirectoryPass()->process($container);
        self::assertSame('mine', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, bool>        $bundles the bundles beside DoctrineBundle and PhilContentBundle
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles + ['DoctrineBundle' => true, 'PhilContentBundle' => true]);
        $this->extension()->load($configs, $container);

        return $container;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function factoryArguments(mixed $definition): array
    {
        self::assertInstanceOf(Definition::class, $definition);

        return $definition->getArguments();
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilEmailTemplatesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
