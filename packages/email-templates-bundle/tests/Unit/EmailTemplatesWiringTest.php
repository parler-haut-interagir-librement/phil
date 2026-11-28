<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateEngine;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\FixedSiteScope;
use Phil\EmailTemplatesBundle\Application\Preview\SamplePreviews;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Infrastructure\Audit\AuditTemplateChanges;
use Phil\EmailTemplatesBundle\Infrastructure\Console\CheckTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\ListTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Content\ContentTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplateKeysCollation;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\EmailTemplatesServices;
use Phil\EmailTemplatesBundle\Infrastructure\Filesystem\DirectoryEmbedLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Markup\MarkupMarkdown;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmailHandler;
use Phil\EmailTemplatesBundle\Infrastructure\Tenancy\TenancySiteScope;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\AdminExtension;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\KitComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\TenancyBundle\Application\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Every argument, factory and visibility the container receives, one by one.
 */
#[CoversClass(PhilEmailTemplatesBundle::class)]
#[CoversClass(EmailTemplatesServices::class)]
final class EmailTemplatesWiringTest extends TestCase
{
    public function testTheServicesOfTheDefaultConfiguration(): void
    {
        $container = $this->load([['defaults_paths' => ['/one', '/two']]]);

        self::assertSame(['/one', '/two', EmailTemplatesServices::BUNDLE_DEFAULTS], $container->getDefinition(YamlDefaultTemplates::class)->getArgument(0));
        self::assertTrue($container->getAlias(DefaultTemplates::class)->isPublic());
        self::assertTrue($container->getAlias(TemplateEngine::class)->isPublic());
        self::assertTrue($container->getAlias(TemplateStore::class)->isPublic());
        self::assertTrue($container->getAlias(SiteScope::class)->isPublic());
        self::assertTrue($container->getAlias(PreviewRenderer::class)->isPublic());

        self::assertEquals([
            new Reference(TemplateStore::class),
            new Reference(DefaultTemplates::class),
            new Definition(Locale::class)->setFactory([Locale::class, 'fromString'])->setArguments(['en']),
        ], $container->getDefinition(TemplateResolver::class)->getArguments());

        self::assertCount(2, $container->getDefinition(TemplateValidator::class)->getArguments());
        self::assertEquals([new Reference(TemplateEngine::class), new Reference(EmailRenderer::class)], $container->getDefinition(TemplateValidator::class)->getArguments());

        self::assertTrue($container->hasDefinition(SamplePreviews::class));
        self::assertEquals([new Reference(TemplateResolver::class), new Reference(EmailRenderer::class)], $container->getDefinition(SamplePreviews::class)->getArguments());

        self::assertEquals([new Reference(EmailTemplates::class)], $container->getDefinition(ListTemplatesCommand::class)->getArguments());
        self::assertEquals([new Reference(DefaultTemplates::class), new Reference(TemplateValidator::class)], $container->getDefinition(CheckTemplatesCommand::class)->getArguments());

        $theme = $container->getDefinition(EmailTemplatesServices::THEME);
        self::assertSame([Theme::class, 'create'], $theme->getFactory());

        $markup = $container->getDefinition(EmailTemplatesServices::MARKUP);
        self::assertSame(Markup::class, $markup->getClass());
        self::assertEquals([
            new Definition(DefaultCompiler::class),
            new Definition(SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']),
        ], $markup->getArguments());
        self::assertEquals([new Reference(EmailTemplatesServices::MARKUP)], $container->getDefinition(MarkupMarkdown::class)->getArguments());
    }

    public function testTheLayoutOfTheBundleHasItsOwnTwig(): void
    {
        $container = $this->load([]);

        $loader = $container->getDefinition(EmailTemplatesServices::LAYOUT_TWIG.'.loader');
        self::assertSame(FilesystemLoader::class, $loader->getClass());
        self::assertSame([['addPath', [\dirname(EmailTemplatesServices::BUNDLE_DEFAULTS).'/templates', 'PhilEmailTemplates']]], $loader->getMethodCalls());
        self::assertSame(realpath(\dirname(__DIR__, 2).'/templates'), realpath(\dirname(EmailTemplatesServices::BUNDLE_DEFAULTS).'/templates'));

        $twig = $container->getDefinition(EmailTemplatesServices::LAYOUT_TWIG);
        self::assertSame(Environment::class, $twig->getClass());
        self::assertEquals([new Reference(EmailTemplatesServices::LAYOUT_TWIG.'.loader'), ['strict_variables' => true]], $twig->getArguments());
    }

    public function testTheMemoryStore(): void
    {
        $container = $this->load([['store' => 'memory']]);

        self::assertTrue($container->hasDefinition(InMemoryTemplateStore::class));
        self::assertTrue($container->getAlias(TemplateStore::class)->isPublic());
    }

    public function testAStoreOfTheApplicationIsPublic(): void
    {
        self::assertTrue($this->load([['store' => 'app.store']])->getAlias(TemplateStore::class)->isPublic());
    }

    public function testAPreviewOfTheApplicationIsPublic(): void
    {
        self::assertTrue($this->load([['preview' => 'app.preview']])->getAlias(PreviewRenderer::class)->isPublic());
    }

    public function testTheStoreOfTheContentsAndTheCollationOfItsKeys(): void
    {
        $container = $this->load([['entity_manager' => 'doctrine.orm.mail_entity_manager']]);

        self::assertSame(ContentTemplateStore::class, (string) $container->getAlias(TemplateStore::class));
        self::assertTrue($container->getAlias(TemplateStore::class)->isPublic());
        self::assertEquals([
            new Reference('doctrine.orm.mail_entity_manager'),
            new Reference(ContentManager::class),
            new Reference(ContentTransaction::class),
            new Reference(ContentRootRepository::class),
            new Reference(DimensionContentRepository::class),
        ], $container->getDefinition(ContentTemplateStore::class)->getArguments());
        self::assertSame([['event' => 'loadClassMetadata']], $container->getDefinition(TemplateKeysCollation::class)->getTag('doctrine.event_listener'));
        self::assertEquals(new Reference('doctrine.orm.entity_manager'), $this->load([[]])->getDefinition(ContentTemplateStore::class)->getArgument(0));
        self::assertEquals(new Reference('clock'), $this->load([['store' => 'memory']])->getDefinition(InMemoryTemplateStore::class)->getArgument(0));
        self::assertFalse($this->load([['store' => 'memory']])->hasDefinition(TemplateKeysCollation::class));
    }

    /**
     * @return iterable<string, array{array<string, bool>}>
     */
    public static function bundlesWithoutTheContents(): iterable
    {
        yield 'neither' => [[]];
        yield 'Doctrine alone' => [['DoctrineBundle' => true]];
        yield 'the contents alone' => [['PhilContentBundle' => true]];
    }

    /**
     * @param array<string, bool> $bundles
     */
    #[DataProvider('bundlesWithoutTheContents')]
    public function testTheStoreOfTheContentsNeedsDoctrineAndTheContents(array $bundles): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The templates are kept as contents of phil/content-bundle: enable DoctrineBundle and PhilContentBundle, or set "phil_email_templates.store" to "memory" or to a TemplateStore service.');

        $this->load([[]], $bundles, false);
    }

    public function testTheEntitiesAndTheRootArePrependedWithDoctrineAndTheContents(): void
    {
        $container = $this->prepend(['DoctrineBundle' => true, 'PhilContentBundle' => true]);

        $doctrine = $container->getExtensionConfig('doctrine');
        self::assertCount(1, $doctrine);
        self::assertIsArray($doctrine[0]);
        $orm = $doctrine[0]['orm'] ?? null;
        self::assertIsArray($orm);
        self::assertIsArray($orm['mappings']);
        $mapping = $orm['mappings']['PhilEmailTemplatesBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertSame(['type', 'is_bundle', 'dir', 'prefix'], array_keys($mapping));
        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertIsString($mapping['dir']);
        self::assertSame(realpath(\dirname(__DIR__, 2).'/src/Infrastructure/Content/Entity'), realpath($mapping['dir']));
        self::assertSame('Phil\\EmailTemplatesBundle\\Infrastructure\\Content\\Entity', $mapping['prefix']);
        self::assertSame([['roots' => [EmailTemplateContent::class]]], $container->getExtensionConfig('phil_content'));

        foreach ([[], ['DoctrineBundle' => true], ['PhilContentBundle' => true]] as $bundles) {
            $without = $this->prepend($bundles);
            self::assertSame([], $without->getExtensionConfig('doctrine'));
            self::assertSame([], $without->getExtensionConfig('phil_content'));
        }
    }

    public function testTheSiteOfTheTenant(): void
    {
        $container = $this->load([['site_scope' => 'tenancy']]);

        self::assertEquals([new Reference(TenantContext::class)], $container->getDefinition(TenancySiteScope::class)->getArguments());
        self::assertTrue($container->getAlias(SiteScope::class)->isPublic());
        self::assertSame(TenancySiteScope::class, (string) $container->getAlias(SiteScope::class));
    }

    public function testTheSiteOfTheTenantFoundByDefault(): void
    {
        $container = $this->load([['site_scope' => 'auto']], ['PhilTenancyBundle' => true]);

        self::assertSame(TenancySiteScope::class, (string) $container->getAlias(SiteScope::class));
        self::assertSame(FixedSiteScope::class, (string) $this->load([['site_scope' => 'auto']])->getAlias(SiteScope::class));
        self::assertSame(FixedSiteScope::class, (string) $this->load([['site_scope' => 'fixed']], ['PhilTenancyBundle' => true])->getAlias(SiteScope::class));
    }

    public function testTheFixedSite(): void
    {
        $container = $this->load([['site' => 'main']]);

        self::assertEquals(new Definition(Site::class)->setFactory([Site::class, 'fromString'])->setArguments(['main']), $container->getDefinition(FixedSiteScope::class)->getArgument(0));
        self::assertTrue($container->getAlias(SiteScope::class)->isPublic());
    }

    public function testTheComposerSender(): void
    {
        $container = $this->load([['from' => 'shop@example.com']]);

        self::assertEquals(new Definition(Address::class)->setFactory([Address::class, 'create'])->setArguments(['shop@example.com']), $container->getDefinition(EmailComposer::class)->getArgument(1));
    }

    public function testTheEmbeddedImages(): void
    {
        $container = $this->load([['embeds' => ['directory' => '/images']]]);

        self::assertSame(['/images', 1_048_576], $container->getDefinition(DirectoryEmbedLoader::class)->getArguments());
        self::assertSame(DirectoryEmbedLoader::class, (string) $container->getAlias(EmbedLoader::class));
    }

    public function testTheAuditListensToBothEvents(): void
    {
        $container = $this->load([[]], ['PhilAuditBundle' => true]);

        self::assertSame(
            [
                ['event' => TemplateSaved::class, 'method' => 'saved'],
                ['event' => TemplatePublished::class, 'method' => 'published'],
                ['event' => TemplateDraftDiscarded::class, 'method' => 'discarded'],
                ['event' => TemplateReset::class, 'method' => 'reset'],
            ],
            $container->getDefinition(AuditTemplateChanges::class)->getTag('kernel.event_listener'),
        );
        self::assertEquals([new Reference(AuditTrail::class), 'email_templates'], $container->getDefinition(AuditTemplateChanges::class)->getArguments());
    }

    public function testTheMessengerHandler(): void
    {
        $container = $this->load([['messenger' => ['delivery_resolver' => 'my_resolver']]]);

        self::assertEquals(
            [new Reference(DeliveryResolver::class), new Reference(EmailComposer::class), new Reference(MailerInterface::class)],
            $container->getDefinition(SendTemplatedEmailHandler::class)->getArguments(),
        );
    }

    public function testTheAdministrationExtension(): void
    {
        $container = $this->load([['admin' => ['enabled' => true]]]);

        self::assertEquals([new Reference(KitComponents::class)], $container->getDefinition(AdminExtension::class)->getArguments());
    }

    public function testTheDispatcherIsOptional(): void
    {
        $container = $this->load([[]]);

        self::assertEquals(new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(EmailTemplates::class)->getArgument(6));
    }

    public function testTheInformationOfTheOptions(): void
    {
        $tree = $this->tree();

        self::assertSame(
            'Colours replacing those of the light scheme: '.implode(', ', array_keys(Theme::LIGHT)).'.',
            $this->child($this->child($tree, 'theme'), 'light')->getInfo(),
        );
        self::assertSame(
            'What the sandbox allows beyond its own list, from the Twig extensions tagged "phil_email_templates.sandbox_extension".',
            $this->child($tree, 'sandbox')->getInfo(),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedValues(): iterable
    {
        yield 'a locale and a line break' => [['default_locale' => "en\n"]];
        yield 'a locale and trailing text' => [['default_locale' => 'fr!']];
        yield 'a site and a line break' => [['site' => "main\n"]];
        yield 'no image byte' => [['embeds' => ['max_bytes' => 0]]];
        yield 'more than 10 MiB of image' => [['embeds' => ['max_bytes' => 10_485_761]]];
        yield 'more than 10 MiB of HTML' => [['max_html_bytes' => 10_485_761]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedValues')]
    public function testARefusedValue(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheBoundariesAreAccepted(): void
    {
        $container = $this->load([['embeds' => ['directory' => '/i', 'max_bytes' => 10_485_760], 'max_html_bytes' => 10_485_760]]);

        self::assertSame(10_485_760, $container->getDefinition(DirectoryEmbedLoader::class)->getArgument(1));
        self::assertSame(10_485_760, $container->getDefinition(EmailRenderer::class)->getArgument(6));
        self::assertSame(1, $this->load([['embeds' => ['directory' => '/i', 'max_bytes' => 1]]])->getDefinition(DirectoryEmbedLoader::class)->getArgument(1));
        self::assertSame(2, $this->load([['embeds' => ['directory' => '/i', 'max_bytes' => 2]]])->getDefinition(DirectoryEmbedLoader::class)->getArgument(1));
    }

    public function testEveryAcceptedEnumeration(): void
    {
        foreach (['auto', 'true', 'false'] as $inline) {
            self::assertTrue($this->load([['inline_css' => $inline]])->hasDefinition(EmailRenderer::class));
        }

        foreach (['auto', 'tenancy', 'fixed'] as $scope) {
            self::assertTrue($this->load([['site_scope' => $scope]])->hasDefinition(EmailRenderer::class));
        }
    }

    public function testTheBundlesWithNumericKeysAreIgnored(): void
    {
        $container = $this->load([['site_scope' => 'auto']], [0 => true, 'PhilTenancyBundle' => true]);

        self::assertSame(TenancySiteScope::class, (string) $container->getAlias(SiteScope::class));
    }

    private function child(NodeInterface $node, string $name): BaseNode
    {
        self::assertInstanceOf(ArrayNode::class, $node);
        $child = $node->getChildren()[$name];
        self::assertInstanceOf(BaseNode::class, $child);

        return $child;
    }

    private function tree(): NodeInterface
    {
        $extension = new PhilEmailTemplatesBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return $configuration->getConfigTreeBuilder()->buildTree();
    }

    /**
     * @param array<array-key, bool> $bundles
     */
    private function prepend(array $bundles): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        $extension = new PhilEmailTemplatesBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<array-key, bool>     $bundles      the bundles beside DoctrineBundle and PhilContentBundle
     * @param bool                       $withContents whether DoctrineBundle and PhilContentBundle are enabled
     */
    private function load(array $configs, array $bundles = [], bool $withContents = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $withContents ? $bundles + ['DoctrineBundle' => true, 'PhilContentBundle' => true] : $bundles);

        $extension = new PhilEmailTemplatesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load($configs, $container);

        return $container;
    }
}
