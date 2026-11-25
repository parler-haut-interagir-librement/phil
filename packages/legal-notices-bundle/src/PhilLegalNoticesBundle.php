<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle;

use Override;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\IntlExtensionPass;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\LegalNoticesBundle\Infrastructure\DependencyInjection\LegalNoticesServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The legal notice and the privacy policy of a site, wired from its configuration:
 *
 * ```yaml
 * phil_legal_notices:
 *     identity:
 *         publisher_name: Example Inc.
 *         publisher_address: "1 Main Street\nMontréal QC"
 *         publication_director: Jo Smith
 *         contact_email: hello@example.com
 *         host_name: Example Hosting
 *         host_address: 2 Server Road, Toronto ON
 *         host_contact: support@hosting.example
 *         dpo_name: Jo Smith
 *         dpo_contact: privacy@example.com
 *     documents:
 *         legal_notice:
 *             - { number: 1, published_at: '2026-12-01', files: { en: '%kernel.project_dir%/legal/notice.en.md' }, format: markdown }
 *     retention: { source: App\Privacy\ScheduleSource }
 *     consent:
 *         categories:
 *             necessary: { necessary: true, labels: { en: Necessary, fr: Nécessaires }, cookies: [PHPSESSID] }
 * ```
 *
 * The pages are mounted from `@PhilLegalNoticesBundle/config/routes.php`; the footer links come from the Twig function
 * `phil_legal_notices_links()`; the bodies are rendered by `phil/markup` when it is installed. The pages draw their
 * tables and badges with recipes of the PHIL UX kit — or the Shadcn kit — installed by the Symfony UX Toolkit (`RECIPES`).
 */
final class PhilLegalNoticesBundle extends AbstractBundle
{
    public const string LAYOUT = '@PhilLegalNotices/layout.html.twig';

    /**
     * The recipes of the kit the templates draw, as `ux:install` names them.
     */
    public const array RECIPES = ['badge', 'table'];

    #[Override]
    protected string $extensionAlias = 'phil_legal_notices';

    /**
     * The recipes of the kit are named after the components subdirectory of the Toolkit; the extension of twig/intl-extra
     * is left to the application when it registers its own.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        // Priority 1: before the TwigEnvironmentPass of TwigBundle (priority 0), which adds the extensions to Twig.
        $container->addCompilerPass(new IntlExtensionPass(), priority: 1);
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->scalarNode('default_locale')->info('The locale whose body a version shows when it has none in the locale of the request.')->defaultValue('en')->cannotBeEmpty()->end()
                ->arrayNode('identity')
                    ->info('The fields of the identity of the site, by name: publisher_name, publisher_address, publication_director, contact_email, host_name, dpo_name…')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('field')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('source')->info('Service id of a DocumentSource the versions are read from, instead of the documents of the configuration.')->defaultNull()->end()
                ->arrayNode('documents')
                    ->info('The versions of each document, legal_notice and privacy_policy.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('type')
                    ->arrayPrototype()
                        ->arrayPrototype()
                            ->children()
                                ->integerNode('number')->isRequired()->end()
                                ->scalarNode('format')->defaultValue('text')->cannotBeEmpty()->end()
                                ->enumNode('state')->values(['draft', 'published', 'withdrawn'])->defaultValue('published')->end()
                                ->scalarNode('published_at')->defaultNull()->end()
                                ->scalarNode('effective_at')->defaultNull()->end()
                                ->arrayNode('bodies')->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                                ->arrayNode('files')->info('A file of the body of each locale, read when the container is built.')->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                                ->arrayNode('summaries')->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('retention')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('source')->info('Service id of the RetentionScheduleSource of phil/privacy-bundle, or of a RetentionDurations.')->defaultNull()->end()
                        ->booleanNode('personal_only')->info('Lists only the rules that hold personal information.')->defaultTrue()->end()
                        ->arrayNode('labels')
                            ->info('What each rule keeps, by rule then locale; its store otherwise.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('rule')
                            ->arrayPrototype()->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('consent')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('source')->info('Service id of a ConsentCategories, instead of the categories of the configuration.')->defaultNull()->end()
                        ->arrayNode('categories')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('key')
                            ->arrayPrototype()
                                ->children()
                                    ->booleanNode('necessary')->defaultFalse()->end()
                                    ->arrayNode('labels')->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                                    ->arrayNode('descriptions')->normalizeKeys(false)->useAttributeAsKey('locale')->scalarPrototype()->end()->end()
                                    ->arrayNode('cookies')->scalarPrototype()->end()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('markup')->info('Service id of the phil/markup Markup the bodies are rendered with; one is built when the package is installed.')->defaultNull()->end()
                ->arrayNode('pages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')->info('The Twig layout the pages extend; it defines the "title" and "content" blocks.')->defaultValue(self::LAYOUT)->cannotBeEmpty()->end()
                        ->booleanNode('retention_section')->info('Lists the retention periods under the privacy policy.')->defaultTrue()->end()
                        ->booleanNode('consent_section')->info('Lists the consent categories under the privacy policy.')->defaultTrue()->end()
                        ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('footer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('terms_policies')->info('The policies of phil/terms-bundle the footer links to, after the documents.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                    ->end()
                ->end()
                ->scalarNode('sitemap_section')->info('The section of phil/sitemap-bundle the pages of the documents go in.')->defaultValue('default')->cannotBeEmpty()->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{clock: string, default_locale: string, identity: array<string, string|null>, source: string|null, documents: array<string, list<array{number: int, format: string, state: string, published_at: string|null, effective_at: string|null, bodies: array<string, string>, files: array<string, string>, summaries: array<string, string>}>>, retention: array{source: string|null, personal_only: bool, labels: array<string, array<string, string>>}, consent: array{source: string|null, categories: array<string, array{necessary: bool, labels: array<string, string>, descriptions: array<string, string>, cookies: list<string>}>}, markup: string|null, pages: array{layout: string, retention_section: bool, consent_section: bool, kit_subdirectory: string|null}, footer: array{terms_policies: list<string>}, sitemap_section: string} $config */
        LegalNoticesServices::register($builder, $config);
    }
}
