<?php

declare(strict_types=1);

namespace Phil\TranslationBundle;

use Override;
use Phil\TranslationBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\TranslationBundle\Infrastructure\DependencyInjection\TranslationServices;
use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Variants;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Translation by segments for an application: the configuration sets where segments, glossary terms and the memory
 * are kept, how documents are cut, how close a match must be, the regional variants that inherit from their parent,
 * and the services of the application behind the translation screen — its documents, who may translate, who the
 * translators are, the inflector of glossary terms and the machine translator, off unless one is named. The tables are mapped for Doctrine, the screen's CSRF
 * token is added to the stateless ones of FrameworkBundle, and with AssetMapper its Stimulus controller is served
 * without a build step.
 */
final class PhilTranslationBundle extends AbstractBundle
{
    public const string DEFAULT_LAYOUT = '@PhilTranslation/layout.html.twig';

    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_translation';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where segments, terms and the memory are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the tables.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->enumNode('granularity')
                    ->info('How documents are cut: one segment per text field of a block, or one per sentence.')
                    ->values(['block', 'sentence'])
                    ->defaultValue('block')
                ->end()
                ->booleanNode('prefill')
                    ->info('Whether new segments are proposed the exact matches of the memory.')
                    ->defaultTrue()
                ->end()
                ->floatNode('min_score')
                    ->info('The lowest score of a fuzzy match, above 0 and at most 1.')
                    ->min(0.01)
                    ->max(1.0)
                    ->defaultValue(0.7)
                ->end()
                ->integerNode('max_suggestions')
                    ->info('The most matches proposed for a segment.')
                    ->min(1)
                    ->defaultValue(5)
                ->end()
                ->scalarNode('sources')
                    ->info('The service, a SourceDocuments, that finds the documents to translate; the screen needs it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('policy')
                    ->info('The service, a TranslationPolicy, that says who may translate and review; the screen needs it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The Twig layout the screen extends; it defines the "title" and "content" blocks.')
                    ->cannotBeEmpty()
                    ->defaultValue(self::DEFAULT_LAYOUT)
                ->end()
                ->arrayNode('variants')
                    ->info('The regional variants, as BCP 47 tags, that inherit the targets of their parent language.')
                    ->scalarPrototype()->end()
                    ->defaultValue(Variants::DEFAULT)
                    ->validate()
                        ->ifTrue(static function (array $variants): bool {
                            try {
                                new Variants(array_map(static fn (mixed $variant): string => \is_string($variant) ? $variant : '', $variants));

                                return false;
                            } catch (InvalidLanguage) {
                                return true;
                            }
                        })
                        ->thenInvalid('Each variant is a language tag with a parent, such as "fr-CA"; got %s.')
                    ->end()
                ->end()
                ->scalarNode('translators')
                    ->info('The service, a Translators, that names the current user and the credit of each translator.')
                    ->defaultNull()
                ->end()
                ->scalarNode('inflector')
                    ->info('The service, an Inflector, that adds the inflected forms of glossary terms, such as Phil\\TranslationMemory\\Glossary\\RegularPlurals.')
                    ->defaultNull()
                ->end()
                ->arrayNode('machine_translation')
                    ->info('Machine translation of untranslated segments, proposed and marked as machine output; off by default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('translator')
                            ->info('The service, a MachineTranslator, an adapter named after its service.')
                            ->defaultNull()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $machine): bool => true === $machine['enabled'] && null === $machine['translator'])
                        ->thenInvalid('Machine translation needs its translator service: %s.')
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilTranslationBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\TranslationBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [SameOriginGuard::TOKEN_ID]]]);
        }

        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::CONTROLLERS_DIR => '@phil/translation-bundle']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, entity_manager: string, granularity: string, prefill: bool, min_score: float, max_suggestions: int, sources: ?string, policy: ?string, layout: string, variants: list<string>, translators: ?string, inflector: ?string, machine_translation: array{enabled: bool, translator: ?string}} $config */
        TranslationServices::register($builder, $config);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(SameOriginGuard::class, SameOriginGuard::TOKEN_ID));
    }
}
