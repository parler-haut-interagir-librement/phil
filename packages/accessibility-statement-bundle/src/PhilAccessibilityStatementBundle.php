<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle;

use Override;
use Phil\AccessibilityStatementBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AccessibilityStatementBundle\Infrastructure\DependencyInjection\StatementServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The accessibility statement of a Symfony application, wired from its configuration:
 *
 * ```yaml
 * phil_accessibility_statement:
 *     wording: en-301-549
 *     feedback_route: phil_contact_form
 *     statement:
 *         version: 1
 *         published_at: '2026-11-08'
 *         entity: Example Org
 *         feedback: { email: accessibility@example.com, response_days: 10 }
 *     audit: { enabled: true }
 * ```
 *
 * The statement is read from the configuration, or from the table `phil_accessibility_statement` with the `doctrine`
 * source, the configuration then giving the first version. The page is served at `/accessibility` in English and
 * `/accessibilite` in French; the command `phil:accessibility-statement:generate` writes the next version from the
 * reports of phil/accessibility-check. The templates draw their parts with recipes of the PHIL UX kit — or the Shadcn
 * kit — installed by the Symfony UX Toolkit; with `admin.enabled`, an administration page publishes the next version.
 *
 * @phpstan-import-type Config from StatementServices
 */
final class PhilAccessibilityStatementBundle extends AbstractBundle
{
    public const string LAYOUT = '@PhilAccessibilityStatement/layout.html.twig';

    public const array WORDINGS = ['wcag', 'en-301-549', 'aca'];

    #[Override]
    protected string $extensionAlias = 'phil_accessibility_statement';

    /**
     * The recipes of the kit are named after the components subdirectory of the Toolkit.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('source')->info('Where the versions are kept: "config", the statement below, or "doctrine", a table.')->values(['config', 'doctrine'])->defaultValue('config')->end()
                ->scalarNode('connection')->info('The DBAL connection of the table of the "doctrine" source.')->defaultValue('default')->cannotBeEmpty()->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock that dates a regenerated version.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->scalarNode('default_locale')->info("The locale whose text the page shows when a text has none in the request's locale.")->defaultValue('en')->cannotBeEmpty()->end()
                ->enumNode('wording')->info('The wording of the page: "wcag", "en-301-549" for the European standard, "aca" for the Accessible Canada Act.')->values(self::WORDINGS)->defaultValue('wcag')->end()
                ->scalarNode('layout')->info('The Twig layout the page extends; it defines the "title" and "content" blocks.')->defaultValue(self::LAYOUT)->cannotBeEmpty()->end()
                ->integerNode('cache_max_age')->info('The seconds browsers and shared caches may keep the page; 0 makes them ask each time.')->min(0)->defaultValue(3600)->end()
                ->scalarNode('feedback_route')->info('The route of a page that takes feedback, such as the contact form of phil/contact-form-bundle.')->defaultNull()->end()
                ->scalarNode('feedback_form')
                    ->info('The key of a form of phil/forms-bundle that takes feedback: the page links to its page, the route "phil_forms_form".')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $key): bool => null !== $key && (!\is_string($key) || 1 !== preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $key)))
                        ->thenInvalid('The key of a form is 1 to 64 lower-case letters, digits or "-".')
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')->defaultNull()->end()
                ->arrayNode('admin')
                    ->info('The administration page that publishes the next version; with the "config" source, it shows the version to write there.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('role')->info('The role or attribute Symfony Security grants to open the page.')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->variableNode('statement')
                    ->info('The statement, with the keys of StatementReader: required with the "config" source, the first version with "doctrine".')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $statement): bool => null !== $statement && !\is_array($statement))
                        ->thenInvalid('The statement is a map.')
                    ->end()
                ->end()
                ->arrayNode('audit')
                    ->info('The latest audit the command regenerates the statement from.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('source')->info('Service id of an AuditSource, instead of the directory of reports.')->defaultNull()->end()
                        ->scalarNode('directory')->info('The directory of the JSON reports of phil/accessibility-check, one per page.')->defaultValue('%kernel.project_dir%/var/accessibility')->cannotBeEmpty()->end()
                        ->enumNode('minimum_severity')->info('The severity from which a finding is a non-conformity.')->values(['error', 'warning', 'notice'])->defaultValue('error')->end()
                        ->enumNode('method')->info('How the audit evaluates the content.')->values(['automated', 'self-assessment', 'third-party'])->defaultValue('automated')->end()
                        ->scalarNode('report_url')->info('The address where the report of the audit is published.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        StatementServices::register($builder, $config);
    }
}
