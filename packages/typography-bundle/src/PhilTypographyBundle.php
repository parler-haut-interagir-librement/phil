<?php

declare(strict_types=1);

namespace Phil\TypographyBundle;

use Override;
use Phil\TypographyBundle\Infrastructure\DependencyInjection\TypographyServices;
use Phil\TypographyBundle\Infrastructure\DependencyInjection\UjoFilterPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/typography` in a Symfony application: one typographer per locale from the configuration, the `typography` and
 * `typography_text` Twig filters, and the typographic fixes applied to the HTML that `phil/ujo-bundle` renders, both
 * in the current locale of the request unless told otherwise.
 */
final class PhilTypographyBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_typography';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_locale')
                    ->info("The locale of the default typographer, which the filters use when Symfony's LocaleSwitcher gives none; the kernel's default locale when null.")
                    ->defaultNull()
                ->end()
                ->booleanNode('hyphenation')
                    ->info('Whether the presets insert soft hyphens in the languages the library has patterns for.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('experimental_hyphenation')
                    ->info('The experimental hyphenation patterns to serve, by tag; none by default.')
                    ->enumPrototype()->values(TypographyServices::EXPERIMENTAL)->end()
                ->end()
                ->arrayNode('protected_tags')
                    ->info('The elements whose text is never changed.')
                    ->scalarPrototype()->end()
                    ->defaultValue(TypographyServices::PROTECTED_TAGS)
                ->end()
                ->arrayNode('locales')
                    ->info('A typographer service for each locale, with its fixers by name in the order they run; an empty list keeps the preset of the locale.')
                    ->useAttributeAsKey('locale')
                    ->arrayPrototype()
                        ->enumPrototype()->values(TypographyServices::FIXERS)->end()
                    ->end()
                ->end()
                ->arrayNode('ujo')
                    ->info('The typographic fixes applied to the HTML of UJO documents, when phil/ujo-bundle is installed.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('locale')
                            ->info("The locale of the fixes; the current locale of Symfony's LocaleSwitcher, then the default locale, when null.")
                            ->defaultNull()
                        ->end()
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
        /** @var array{default_locale: ?string, hyphenation: bool, experimental_hyphenation: list<string>, protected_tags: list<string>, locales: array<string, list<string>>, ujo: array{enabled: bool, locale: ?string}} $config */
        TypographyServices::register(
            $builder,
            $config['default_locale'],
            $config['hyphenation'],
            $config['protected_tags'],
            $config['locales'],
            $config['ujo']['enabled'] ? $config['ujo']['locale'] ?? '' : null,
            $config['experimental_hyphenation'],
        );
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new UjoFilterPass());
    }
}
