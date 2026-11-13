<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle;

use Override;
use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Exception\PrintPreflightException;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflightBundle\Application\AsPreflightCheck;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use Phil\PrintPreflightBundle\Infrastructure\DependencyInjection\FontCataloguePass;
use Phil\PrintPreflightBundle\Infrastructure\DependencyInjection\PrintPreflightServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/print-preflight` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_print_preflight:
 *     profiles:
 *         booklet: { trim_sizes: [a5], bleed: 3mm, page_multiple: 4 }
 * ```
 *
 * It registers the printer profiles, the preflight with the checks marked `#[AsPreflightCheck]`, the reader of measured
 * documents, the command `phil:print-preflight:check`, and the services of the optional integrations.
 */
final class PhilPrintPreflightBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_print_preflight';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_profile')
                    ->info('The profile used when none is named; the first one by default.')
                    ->defaultNull()
                ->end()
                ->arrayNode('profiles')
                    ->info('The printer profiles, by name; one named "default", with the defaults below, when there is none.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('trim_sizes')
                                ->info('The trim sizes the printer prints, each orientation on its own: a4, a5, letter, or such as "148x210mm"; empty for any.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->booleanNode('uniform_trim_size')->info('Every page has the trim size of the first page.')->defaultTrue()->end()
                            ->scalarNode('bleed')->info('The bleed each page carries, such as "3mm" or "0.125in".')->defaultValue('3mm')->end()
                            ->scalarNode('safety_margin')->info('How far inside the trim edge content stays.')->defaultValue('5mm')->end()
                            ->floatNode('minimum_resolution')->info('Pixels per inch below which an image is an error.')->defaultValue(150.0)->end()
                            ->floatNode('target_resolution')->info('Pixels per inch below which an image is a warning.')->defaultValue(300.0)->end()
                            ->floatNode('maximum_resolution')->info('Pixels per inch above which an image is a notice; none when null.')->defaultNull()->end()
                            ->integerNode('minimum_pages')->defaultValue(1)->end()
                            ->integerNode('maximum_pages')->defaultNull()->end()
                            ->integerNode('page_multiple')->info('2 for both sides of a sheet, 4 for saddle stitching.')->defaultValue(1)->end()
                            ->enumNode('colour_workflow')->values(array_map(static fn (ColourWorkflow $workflow): string => $workflow->value, ColourWorkflow::cases()))->defaultValue(ColourWorkflow::Cmyk->value)->end()
                            ->booleanNode('allow_spot_colours')->defaultFalse()->end()
                            ->arrayNode('severities')
                                ->info('The severity of a rule, by its code, such as { page-count.multiple: warning }.')
                                ->useAttributeAsKey('code')
                                ->normalizeKeys(false)
                                ->enumPrototype()->values(array_map(static fn (Severity $severity): string => $severity->value, Severity::cases()))->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('ignore')
                                ->info('The codes of the rules not to report.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->floatNode('maximum_ink_coverage')->info('The total ink coverage, in percent, above which an image or a flat colour is an error in a CMYK workflow.')->defaultValue(300.0)->end()
                            ->scalarNode('minimum_line_width')->info('The thinnest line the printer reproduces, such as "0.25pt" or "0.1mm".')->defaultValue('0.25pt')->end()
                            ->booleanNode('allow_overprint')->info('Whether the printer honours overprint.')->defaultTrue()->end()
                            ->booleanNode('allow_transparency')->info('Whether the printer accepts live transparency, as PDF/X-4 does.')->defaultTrue()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{default_profile: ?string, profiles: array<string, array{trim_sizes: list<string>, uniform_trim_size: bool, bleed: string, safety_margin: string, minimum_resolution: float, target_resolution: float, maximum_resolution: ?float, minimum_pages: int, maximum_pages: ?int, page_multiple: int, colour_workflow: string, allow_spot_colours: bool, severities: array<string, string>, ignore: list<string>, maximum_ink_coverage: float, minimum_line_width: string, allow_overprint: bool, allow_transparency: bool}>} $config */

        // Built once here so that a profile the library refuses fails the container build rather than the first check.
        try {
            PrintProfiles::fromConfiguration($config['profiles'], $config['default_profile']);
        } catch (PrintPreflightException $printPreflightException) {
            throw new InvalidConfigurationException('phil_print_preflight: '.$printPreflightException->getMessage(), $printPreflightException->getCode(), previous: $printPreflightException);
        }

        PrintPreflightServices::register($builder, $config['profiles'], $config['default_profile']);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(AsPreflightCheck::class, static function (ChildDefinition $definition): void {
            $definition->addTag(PrintPreflightServices::CHECK_TAG);
        });
        $container->addCompilerPass(new FontCataloguePass());
    }
}
