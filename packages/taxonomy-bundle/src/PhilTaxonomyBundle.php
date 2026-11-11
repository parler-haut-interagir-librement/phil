<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle;

use Override;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Infrastructure\Form\CategoryType;
use Phil\TaxonomyBundle\Infrastructure\Form\TagsType;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tags and a translated tree of categories for any resource, in Doctrine, with their form types.
 */
final class PhilTaxonomyBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_taxonomy';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('fallback_locale')
                    ->info('The language of a category name when there is none in the language asked.')
                    ->defaultValue('en')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('slugger')
                    ->info('The service id of the slugger that makes tag names and category keys slugs — "phil_slug.slugger.tags" with phil/slug-bundle —; null for the transliterating slugger of phil/slug, cut at 128 characters.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilTaxonomyBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\TaxonomyBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // The port by reflection: the bundle class wires the adapter, and names no class of the domain.
        $services->set(DoctrineTaxonomyRepository::class)->args([new Reference('doctrine.orm.entity_manager')]);
        $port = new ReflectionClass(DoctrineTaxonomyRepository::class)->getInterfaceNames()[0];
        $services->alias($port, DoctrineTaxonomyRepository::class)->public();

        $arguments = [new Reference($port), $config['fallback_locale']];

        if (\is_string($config['slugger'])) {
            $arguments[] = new Reference($config['slugger']);
        }

        $services->set(Taxonomy::class)->args($arguments)->public();
        $services->set(TagsType::class)->tag('form.type');
        $services->set(CategoryType::class)->args([new Reference(Taxonomy::class)])->tag('form.type');
    }
}
