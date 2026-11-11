<?php

declare(strict_types=1);

namespace Phil\MarkupBundle;

use Override;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MarkupServices;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MediaHostsPass;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\SmartPunctuationPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/markup` in a Symfony application: the enabled formats and their levels, the formats add-ons bring, `Markup`, its compiler and purifier to
 * replace by alias, smart punctuation in every format when `phil/typography` is installed, the converter of every
 * format into UJO, the `MarkupFormatType` form field, and the `markup_html`, `markup_purify` and `markup_ujo` filters in
 * Twig.
 */
final class PhilMarkupBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_markup';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->always(MarkupServices::checked(...))
            ->end()
            ->children()
                ->arrayNode('formats')
                    ->info('The formats texts may be written in, by name, in the order a form offers them; every format, the added ones last, when empty.')
                    ->performNoDeepMerging()
                    ->scalarPrototype()->end()
                    ->validate()
                        ->always($this->unique(...))
                    ->end()
                ->end()
                ->scalarNode('default_format')
                    ->info('The format taken when none is named; the first enabled format when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('levels')
                    ->info('The highest level each format is purified at, by format, whatever a template asks: bbcode: forum keeps the headings of a member out. A format not named goes up to all.')
                    ->useAttributeAsKey('format')
                    ->enumPrototype()->values(MarkupServices::LEVELS)->end()
                ->end()
                ->arrayNode('added_formats')
                    ->info('The formats add-ons bring, by name: the label a form shows, translated in the phil_markup domain, and the service of the ReaderProvider that reads the format. An add-on bundle prepends its own.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('reader')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('default_level')
                    ->info('The level the Twig filters purify at when none is named: basic, forum or all.')
                    ->values(MarkupServices::LEVELS)
                    ->defaultValue('all')
                ->end()
                ->arrayNode('smart_punctuation')
                    ->info('Typographic quotes, dashes, ellipses and the spaces of the locale in the HTML of every format, when phil/typography is installed.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('locale')
                            ->info("The locale of the fixes; the current locale of Symfony's LocaleSwitcher, then the default one, when null.")
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->variableNode('media_hosts')
                    ->info("The hosts images may come from, besides the site itself: a list of host names, subdomains included; [] for none; null for any, which lets a member's image call another site from every reader's browser.")
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $hosts): bool => null !== $hosts && !self::isHostList($hosts))
                        ->thenInvalid('The media hosts are null or a list of host names, as cdn.example.com; %s is not.')
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MediaHostsPass());
        $container->addCompilerPass(new SmartPunctuationPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{formats: list<string>, default_format: string|null, levels: array<string, string>, added_formats: array<string, array{label: string, reader: string}>, default_level: string, smart_punctuation: array{enabled: bool, locale: string|null}, media_hosts: list<string>|null} $config */
        MarkupServices::register($builder, $config['default_level'], $config['media_hosts'], $config['formats'], $config['default_format'], $config['levels'], $config['smart_punctuation']['enabled'] ? $config['smart_punctuation']['locale'] : false, $config['added_formats']);
    }

    /**
     * @param list<string> $formats
     *
     * @return list<string>
     */
    private function unique(array $formats): array
    {
        return array_values(array_unique($formats));
    }

    private static function isHostList(mixed $hosts): bool
    {
        return \is_array($hosts) && array_is_list($hosts) && array_all($hosts, static fn (mixed $host): bool => \is_string($host) && 1 === preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $host));
    }
}
