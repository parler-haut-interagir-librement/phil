<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle;

use Override;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\EmailTemplatesServices;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Email templates shared by several sites — a confirmation, a ticket, a cancellation —, kept per site and locale as
 * contents of `phil/content-bundle`, drafted, published and versioned, rendered in sandboxed Twig with email-safe
 * components and inlined styles, into a Symfony Mailer `Email`, and previewed in a sandboxed frame of an administration.
 *
 * ```yaml
 * phil_email_templates:
 *     default_locale: en
 *     defaults_paths: ['%kernel.project_dir%/email_templates']
 *     admin: { enabled: true, role: ROLE_ADMIN }
 * ```
 *
 * @phpstan-import-type Config from EmailTemplatesServices
 */
final class PhilEmailTemplatesBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_email_templates';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services.")
                    ->defaultTrue()
                ->end()
                ->scalarNode('store')
                    ->info('Where the templates are kept: "content", as contents of phil/content-bundle; "memory" for tests; or the identifier of a TemplateStore service.')
                    ->cannotBeEmpty()
                    ->defaultValue(EmailTemplatesServices::STORE_CONTENT)
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The service of the Doctrine entity manager of the contents: the one phil_content.entity_manager names.')
                    ->cannotBeEmpty()
                    ->defaultValue('doctrine.orm.entity_manager')
                ->end()
                ->scalarNode('clock')
                    ->info('The PSR-20 clock that dates the saves of the drafts.')
                    ->cannotBeEmpty()
                    ->defaultValue('clock')
                ->end()
                ->scalarNode('default_locale')
                    ->info('The locale rendered when none is given, and the last one tried.')
                    ->cannotBeEmpty()
                    ->defaultValue('en')
                    ->validate()->ifTrue(static fn (mixed $locale): bool => !\is_string($locale) || 1 !== preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/D', $locale))->thenInvalid('The default locale is a language, optionally followed by a region, as "en" or "fr_CA"; %s is not.')->end()
                ->end()
                ->scalarNode('site')
                    ->info('The site the application is, when phil/tenancy-bundle does not say it; null for the shared scope.')
                    ->defaultNull()
                    ->validate()->ifTrue(static fn (mixed $site): bool => !\is_string($site) || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D', $site))->thenInvalid('A site is 1 to 64 letters, digits, "_", ".", ":" or "-"; %s is not.')->end()
                ->end()
                ->enumNode('site_scope')
                    ->info('How the current site is found: "tenancy", the active tenant of phil/tenancy-bundle; "fixed", the "site" option; "auto", by tenant when PhilTenancyBundle is enabled and "site" is not set.')
                    ->values(['auto', 'tenancy', 'fixed'])
                    ->defaultValue('auto')
                ->end()
                ->arrayNode('defaults_paths')
                    ->info("The directories of the application's default files, <key>.<locale>.yaml, searched before the bundle's own.")
                    ->scalarPrototype()->end()
                    ->defaultValue(['%kernel.project_dir%/email_templates'])
                ->end()
                ->scalarNode('layout')
                    ->info('The template of the house layout, in the Twig of the application.')
                    ->cannotBeEmpty()
                    ->defaultValue('@PhilEmailTemplates/email/layout.html.twig')
                ->end()
                ->arrayNode('brand')
                    ->info('What the default layout shows at the top of every email.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->info('The name of the sender, as text.')->defaultNull()->end()
                        ->scalarNode('logo')
                            ->info('The https address of a logo, 40 pixels high.')
                            ->defaultNull()
                            ->validate()->ifTrue(static fn (mixed $logo): bool => !\is_string($logo) || !str_starts_with($logo, 'https://'))->thenInvalid('The logo is an https address; %s is not.')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('theme')
                    ->info('The colours of the layout and the components, light and dark, and the font.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('light')
                            ->info('Colours replacing those of the light scheme: '.implode(', ', array_keys(Theme::LIGHT)).'.')
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('dark')
                            ->info('Colours replacing those of the dark scheme, for the clients that apply prefers-color-scheme.')
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('font')->cannotBeEmpty()->defaultValue(Theme::FONT)->end()
                    ->end()
                ->end()
                ->arrayNode('embeds')
                    ->info('Where the images the templates embed are read.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')->info('The directory of the images; when null, no template may embed one.')->defaultNull()->end()
                        ->integerNode('max_bytes')->info('The largest image, in bytes.')->min(1)->max(10_485_760)->defaultValue(1_048_576)->end()
                    ->end()
                ->end()
                ->scalarNode('from')
                    ->info('The sender of an email composed without one: "shop@example.com" or "Shop <shop@example.com>".')
                    ->defaultNull()
                ->end()
                ->integerNode('max_html_bytes')
                    ->info('The largest email rendered, in bytes of HTML.')
                    ->min(1024)
                    ->max(10_485_760)
                    ->defaultValue(1_048_576)
                ->end()
                ->arrayNode('sandbox')
                    ->info('What the sandbox allows beyond its own list, from the Twig extensions tagged "'.EmailTemplatesServices::SANDBOX_EXTENSION_TAG.'".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('filters')->scalarPrototype()->end()->end()
                        ->arrayNode('functions')->scalarPrototype()->end()->end()
                        ->booleanNode('cache')->info('Whether the compiled templates are cached under the cache directory.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->enumNode('inline_css')
                    ->info('Whether the styles are inlined with twig/cssinliner-extra: "auto" when it is installed.')
                    ->values(['auto', 'true', 'false'])
                    ->defaultValue('auto')
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Registers the administration and its preview; its routes are in config/routes_admin.php.')->defaultFalse()->end()
                        ->scalarNode('role')->info('The role every page of the administration asks.')->cannotBeEmpty()->defaultValue('ROLE_ADMIN')->end()
                        ->scalarNode('layout')->info('The template the pages extend, with the blocks "title" and "content".')->cannotBeEmpty()->defaultValue('@PhilEmailTemplates/admin/layout.html.twig')->end()
                    ->end()
                ->end()
                ->arrayNode('audit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Records each change in the trail of phil/audit-bundle, when PhilAuditBundle is enabled.')->defaultTrue()->end()
                        ->scalarNode('segment')->cannotBeEmpty()->defaultValue('email_templates')->end()
                    ->end()
                ->end()
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('delivery_resolver')->info('The DeliveryResolver service that handles SendTemplatedEmail; no handler when null.')->defaultNull()->end()
                    ->end()
                ->end()
                ->scalarNode('preview')
                    ->info('A PreviewRenderer service replacing the preview of the bundle.')
                    ->defaultNull()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [AdminController::TOKEN_ID]]]);
        }

        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        // The entities of the templates, and their root among the contents, when both bundles they need are there.
        if (\is_array($bundles) && isset($bundles['DoctrineBundle'], $bundles['PhilContentBundle'])) {
            $builder->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['PhilEmailTemplatesBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Infrastructure/Content/Entity',
                'prefix' => 'Phil\\EmailTemplatesBundle\\Infrastructure\\Content\\Entity',
            ]]]]);
            $builder->prependExtensionConfig('phil_content', ['roots' => [EmailTemplateContent::class]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phil_email_templates.enabled', true === $config['enabled']);

        if (true !== $config['enabled']) {
            return;
        }

        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        /** @var Config $config */
        EmailTemplatesServices::register($builder, $config, \is_array($bundles) ? array_values(array_filter(array_keys($bundles), is_string(...))) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }
}
