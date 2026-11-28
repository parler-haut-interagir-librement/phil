<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
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
use Phil\EmailTemplatesBundle\Infrastructure\Console\RenderTemplateCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Content\ContentTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplateKeysCollation;
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
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Extra\CssInliner\CssInlinerExtension;
use Twig\Loader\FilesystemLoader;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Config array{store: string, entity_manager: string, clock: string, default_locale: string, site: ?string, site_scope: string, defaults_paths: list<string>, layout: string, brand: array{name: ?string, logo: ?string}, theme: array{light: array<string, string>, dark: array<string, string>, font: string}, embeds: array{directory: ?string, max_bytes: positive-int}, from: ?string, max_html_bytes: positive-int, sandbox: array{filters: list<string>, functions: list<string>, cache: bool}, inline_css: string, admin: array{enabled: bool, role: string, layout: string}, audit: array{enabled: bool, segment: string}, messenger: array{delivery_resolver: ?string}, preview: ?string, kit_subdirectory: ?string}
 */
final readonly class EmailTemplatesServices
{
    public const string STORE_CONTENT = 'content';

    public const string STORE_MEMORY = 'memory';

    /** The tag of the Twig extensions the application gives the sandbox. */
    public const string SANDBOX_EXTENSION_TAG = 'phil_email_templates.sandbox_extension';

    public const string THEME = 'phil_email_templates.theme';

    public const string MARKUP = 'phil_email_templates.markup';

    public const string LAYOUT_TWIG = 'phil_email_templates.layout_twig';

    /** The directory of the bundle's own default files. */
    public const string BUNDLE_DEFAULTS = __DIR__.'/../../../defaults';

    private function __construct()
    {
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles the names of the enabled bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        self::store($container, $config, $bundles);
        self::scope($container, $config, $bundles);
        self::rendering($container, $config);

        $container->register(YamlDefaultTemplates::class, YamlDefaultTemplates::class)->setArguments([[...$config['defaults_paths'], self::BUNDLE_DEFAULTS]]);
        $container->setAlias(DefaultTemplates::class, YamlDefaultTemplates::class)->setPublic(true);
        $container->register(TemplateResolver::class, TemplateResolver::class)
            ->setArguments([new Reference(TemplateStore::class), new Reference(DefaultTemplates::class), new Definition(Locale::class)->setFactory([Locale::class, 'fromString'])->setArguments([$config['default_locale']])])
            ->setPublic(true);
        $container->register(EmailRenderer::class, EmailRenderer::class)
            ->setArguments([
                new Reference(TemplateResolver::class),
                new Reference(TemplateEngine::class),
                new Reference(EmailLayout::class),
                new Reference(StyleInliner::class),
                new Reference(SiteScope::class),
                null === $config['embeds']['directory'] ? null : new Reference(EmbedLoader::class),
                $config['max_html_bytes'],
            ])
            ->setPublic(true);
        $container->register(TemplateValidator::class, TemplateValidator::class)->setArguments([new Reference(TemplateEngine::class), new Reference(EmailRenderer::class)])->setPublic(true);
        $container->register(EmailComposer::class, EmailComposer::class)
            ->setArguments([new Reference(EmailRenderer::class), null === $config['from'] ? null : new Definition(Address::class)->setFactory([Address::class, 'create'])->setArguments([$config['from']])])
            ->setPublic(true);
        $container->register(EmailTemplates::class, EmailTemplates::class)
            ->setArguments([
                new Reference(TemplateStore::class),
                new Reference(TemplateResolver::class),
                new Reference(TemplateValidator::class),
                new Reference(DefaultTemplates::class),
                new Reference(SiteScope::class),
                new Reference($config['clock']),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        if (null === $config['preview']) {
            $container->register(SamplePreviews::class, SamplePreviews::class)->setArguments([new Reference(TemplateResolver::class), new Reference(EmailRenderer::class)]);
            $container->setAlias(PreviewRenderer::class, SamplePreviews::class)->setPublic(true);
        } else {
            $container->setAlias(PreviewRenderer::class, $config['preview'])->setPublic(true);
        }

        self::console($container, $config);
        self::audit($container, $config, $bundles);
        self::messenger($container, $config);
        self::admin($container, $config);
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles
     */
    private static function store(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryTemplateStore::class, InMemoryTemplateStore::class)->setArguments([new Reference($config['clock'])]);
            $container->setAlias(TemplateStore::class, InMemoryTemplateStore::class)->setPublic(true);

            return;
        }

        if (self::STORE_CONTENT !== $config['store']) {
            $container->setAlias(TemplateStore::class, $config['store'])->setPublic(true);

            return;
        }

        if (!\in_array('DoctrineBundle', $bundles, true) || !\in_array('PhilContentBundle', $bundles, true)) {
            throw new InvalidConfigurationException('The templates are kept as contents of phil/content-bundle: enable DoctrineBundle and PhilContentBundle, or set "phil_email_templates.store" to "memory" or to a TemplateStore service.');
        }

        $container->register(ContentTemplateStore::class, ContentTemplateStore::class)->setArguments([
            new Reference($config['entity_manager']),
            new Reference(ContentManager::class),
            new Reference(ContentTransaction::class),
            new Reference(ContentRootRepository::class),
            new Reference(DimensionContentRepository::class),
        ]);
        $container->setAlias(TemplateStore::class, ContentTemplateStore::class)->setPublic(true);
        $container->register(TemplateKeysCollation::class, TemplateKeysCollation::class)
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles
     */
    private static function scope(ContainerBuilder $container, array $config, array $bundles): void
    {
        $tenancy = class_exists(TenantContext::class) && \in_array('PhilTenancyBundle', $bundles, true);

        if ('tenancy' === $config['site_scope'] || ('auto' === $config['site_scope'] && $tenancy && null === $config['site'])) {
            $container->register(TenancySiteScope::class, TenancySiteScope::class)->setArguments([new Reference(TenantContext::class)]);
            $container->setAlias(SiteScope::class, TenancySiteScope::class)->setPublic(true);

            return;
        }

        $container->register(FixedSiteScope::class, FixedSiteScope::class)
            ->setArguments([new Definition(Site::class)->setFactory([Site::class, 'fromString'])->setArguments([$config['site'] ?? ''])]);
        $container->setAlias(SiteScope::class, FixedSiteScope::class)->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function rendering(ContainerBuilder $container, array $config): void
    {
        $container->register(self::THEME, Theme::class)
            ->setFactory([Theme::class, 'create'])
            ->setArguments([$config['theme']['light'], $config['theme']['dark'], $config['theme']['font']]);

        $container->register(self::MARKUP, Markup::class)
            ->setArguments([new Definition(DefaultCompiler::class), new Definition(SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies'])]);
        $container->register(MarkupMarkdown::class, MarkupMarkdown::class)->setArguments([new Reference(self::MARKUP)]);
        $container->setAlias(MarkdownRenderer::class, MarkupMarkdown::class);

        $container->register(SandboxedEngine::class, SandboxedEngine::class)
            ->setArguments([
                new Reference(self::THEME),
                new Reference(MarkdownRenderer::class),
                $config['sandbox']['cache'] ? '%kernel.cache_dir%/phil_email_templates/twig' : false,
                new TaggedIteratorArgument(self::SANDBOX_EXTENSION_TAG),
                $config['sandbox']['filters'],
                $config['sandbox']['functions'],
                $config['max_html_bytes'],
            ])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(TemplateEngine::class, SandboxedEngine::class)->setPublic(true);

        $inline = 'true' === $config['inline_css'] || ('auto' === $config['inline_css'] && class_exists(CssInlinerExtension::class));
        $container->register(StyleInliner::class, $inline ? CssInlinerStyles::class : NoStyleInliner::class);

        if ($container->hasExtension('twig')) {
            $container->setAlias(self::LAYOUT_TWIG, 'twig');
        } else {
            $container->register(self::LAYOUT_TWIG.'.loader', FilesystemLoader::class)->addMethodCall('addPath', [__DIR__.'/../../../templates', 'PhilEmailTemplates']);
            $container->register(self::LAYOUT_TWIG, Environment::class)->setArguments([new Reference(self::LAYOUT_TWIG.'.loader'), ['strict_variables' => true]]);
        }

        $container->register(TwigLayout::class, TwigLayout::class)->setArguments([new Reference(self::LAYOUT_TWIG), new Reference(self::THEME), $config['layout'], $config['brand']]);
        $container->setAlias(EmailLayout::class, TwigLayout::class);

        if (null !== $config['embeds']['directory']) {
            $container->register(DirectoryEmbedLoader::class, DirectoryEmbedLoader::class)->setArguments([$config['embeds']['directory'], $config['embeds']['max_bytes']]);
            $container->setAlias(EmbedLoader::class, DirectoryEmbedLoader::class);
        }
    }

    /**
     * @param Config $config
     */
    private static function console(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        $container->register(ListTemplatesCommand::class, ListTemplatesCommand::class)->setArguments([new Reference(EmailTemplates::class)])->addTag('console.command');
        $container->register(CheckTemplatesCommand::class, CheckTemplatesCommand::class)
            ->setArguments([new Reference(DefaultTemplates::class), new Reference(TemplateValidator::class)])
            ->addTag('console.command');
        $container->register(RenderTemplateCommand::class, RenderTemplateCommand::class)
            ->setArguments([new Reference(PreviewRenderer::class), $config['default_locale']])
            ->addTag('console.command');
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles
     */
    private static function audit(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!$config['audit']['enabled'] || !class_exists(AuditTrail::class) || !\in_array('PhilAuditBundle', $bundles, true)) {
            return;
        }

        $container->register(AuditTemplateChanges::class, AuditTemplateChanges::class)
            ->setArguments([new Reference(AuditTrail::class), $config['audit']['segment']])
            ->addTag('kernel.event_listener', ['event' => TemplateSaved::class, 'method' => 'saved'])
            ->addTag('kernel.event_listener', ['event' => TemplatePublished::class, 'method' => 'published'])
            ->addTag('kernel.event_listener', ['event' => TemplateDraftDiscarded::class, 'method' => 'discarded'])
            ->addTag('kernel.event_listener', ['event' => TemplateReset::class, 'method' => 'reset']);
    }

    /**
     * @param Config $config
     */
    private static function messenger(ContainerBuilder $container, array $config): void
    {
        if (null === $config['messenger']['delivery_resolver'] || !interface_exists(MailerInterface::class)) {
            return;
        }

        $container->setAlias(DeliveryResolver::class, $config['messenger']['delivery_resolver']);
        $container->register(SendTemplatedEmailHandler::class, SendTemplatedEmailHandler::class)
            ->setArguments([new Reference(DeliveryResolver::class), new Reference(EmailComposer::class), new Reference(MailerInterface::class)])
            ->addTag('messenger.message_handler');
    }

    /**
     * @param Config $config
     */
    private static function admin(ContainerBuilder $container, array $config): void
    {
        if (!$config['admin']['enabled'] || !interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(AdminExtension::class, AdminExtension::class)->setArguments([new Reference(KitComponents::class)])->addTag('twig.extension');
        $container->register(AdminController::class, AdminController::class)
            ->setArguments([
                new Reference(EmailTemplates::class),
                new Reference(PreviewRenderer::class),
                new Reference(SiteScope::class),
                new Reference('security.authorization_checker'),
                new Reference('security.token_storage'),
                new Reference(CsrfProtectionPass::TOKEN_MANAGER),
                new Reference('twig'),
                new Reference('router'),
                $config['admin']['role'],
                $config['admin']['layout'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }
}
