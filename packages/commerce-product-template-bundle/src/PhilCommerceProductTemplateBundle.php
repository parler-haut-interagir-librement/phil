<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle;

use LogicException;
use Override;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles;
use Phil\CommerceProductTemplateBundle\Application\Mockups;
use Phil\CommerceProductTemplateBundle\Application\Port\ImageStore;
use Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts;
use Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore;
use Phil\CommerceProductTemplateBundle\Application\PrintProduction;
use Phil\CommerceProductTemplateBundle\Application\ProductGroups;
use Phil\CommerceProductTemplateBundle\Application\TemplateOptions;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryImageStore;
use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryProductionStore;
use Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProduceOnPurchase;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProducePrintFilesHandler;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\PrintCompositor;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Product templates, a module of the catalogue: templates and designs stored, the products of a design on a template
 * generated in the catalogue and kept as one group, their mockups composed by `phil/print-compositor`, and their
 * options declared to the core of commerce through its `SellableOptions`, and the print files and production order of
 * each order produced, at once or by a Messenger message carrying the order's number.
 *
 * The module plugs into `phil/commerce-bundle` and `phil/commerce-catalog-bundle`, and refuses to load without them.
 * With DoctrineBundle, templates, designs, groups and their members are mapped and stored; with AssetMapper, the
 * Stimulus controller that chooses a product of a group is served.
 */
final class PhilCommerceProductTemplateBundle extends AbstractBundle
{
    /** The directory of the Stimulus controller, served by AssetMapper under the name Symfony UX gives it. */
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_product_template';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('sellable_type')
                    ->info('The reference type the catalogue sells products under, whose options the module declares.')
                    ->defaultValue('product')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('default_locale')
                    ->info('The locale products are named in when their design has no language version.')
                    ->defaultValue('en')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('images_directory')
                    ->info('The directory of the store of images: photos of blanks and artworks are read there, mockups written there. No mockups without it.')
                    ->defaultNull()
                ->end()
                ->arrayNode('mockups')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')
                            ->info('The directory of the mockups in the store of images.')
                            ->defaultValue('mockups')
                            ->cannotBeEmpty()
                        ->end()
                        ->floatNode('shading')
                            ->info("The strength of the fabric's shading over the artworks, from 0 to 1.")
                            ->defaultValue(0.8)
                            ->min(0.0)
                            ->max(1.0)
                        ->end()
                        ->integerNode('quality')
                            ->info('The WebP quality of the mockups, from 0 to 100.')
                            ->defaultValue(82)
                            ->min(0)
                            ->max(100)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('production')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')
                            ->info('The private directory print files and production orders are written to, never served as it is. No production without it, nor without images_directory.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('files_prefix')
                            ->info('The directory of the print files in the production store.')
                            ->defaultValue('print-files')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('orders_prefix')
                            ->info('The directory of the production orders in the production store.')
                            ->defaultValue('production-orders')
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('fonts')
                            ->info('The TrueType or OpenType file of each font texts are printed in, by the name text options and print elements give.')
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->booleanNode('on_purchase')
                            ->info('Dispatch ProducePrintFiles when an order is paid in full, on OrderPurchased.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static function (array $config): bool {
                    /** @var array{images_directory: string|null, production: array{directory: string|null}} $config */
                    return null !== $config['production']['directory'] && null === $config['images_directory'];
                })
                ->thenInvalid('production.directory composes print files from the artworks of the store of images: set images_directory too.')
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension;
     * each extension then loads in a container of its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce') || !$builder->hasExtension('phil_commerce_catalog')) {
            throw new LogicException('PhilCommerceProductTemplateBundle is a module of the catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.');
        }

        // The variant selector under the name Symfony UX gives the controllers of a package, for AssetMapper to serve.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/commerce-product-template-bundle']]);
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceProductTemplateBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceProductTemplateBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config as the configuration tree checked it
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{sellable_type: string, default_locale: string, images_directory: string|null, mockups: array{prefix: string, shading: float, quality: int}, production: array{directory: string|null, files_prefix: string, orders_prefix: string, fonts: array<string, string>, on_purchase: bool}} $config */
        $services = $container->services();
        $services->set(VariantPlanner::class);

        // Every other service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(CatalogGeneration::class)->autowire()->arg('$defaultLocale', $config['default_locale'])->public();
        $services->set(ProductGroups::class)->autowire()->public();
        // Tagged by the core's autoconfiguration: the options of the type the catalogue sells.
        $services->set(TemplateOptions::class)->autowire()->autoconfigure()->arg('$sellableType', $config['sellable_type']);

        if (null !== $config['images_directory']) {
            $services->set(DirectoryImageStore::class)->args([$config['images_directory']]);
            $services->alias(ImageStore::class, DirectoryImageStore::class)->public();
            $services->set(MockupCompositor::class);
            $services->set(Mockups::class)->autowire()
                ->arg('$prefix', $config['mockups']['prefix'])
                ->arg('$shading', $config['mockups']['shading'])
                ->arg('$quality', $config['mockups']['quality'])
                ->public();

            if (null !== $config['production']['directory']) {
                $this->loadProduction($config['sellable_type'], $config['default_locale'], $config['production'], $services);
            }
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineProductTemplateRepository::class, DoctrineProductDesignRepository::class, DoctrineProductGroupRepository::class] as $adapter) {
            $services->set($adapter)->args([service('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }
    }

    /**
     * @param array{directory: string|null, files_prefix: string, orders_prefix: string, fonts: array<string, string>, on_purchase: bool} $production
     */
    private function loadProduction(string $sellableType, string $defaultLocale, array $production, ServicesConfigurator $services): void
    {
        $services->set(DirectoryProductionStore::class)->args([$production['directory']]);
        $services->alias(ProductionStore::class, DirectoryProductionStore::class)->public();
        $services->set(ConfiguredFonts::class)->args([$production['fonts']]);
        $services->alias(PrintFonts::class, ConfiguredFonts::class);
        $services->set(PrintCompositor::class);
        $services->set(PrintProduction::class)->autowire()
            ->arg('$sellableType', $sellableType)
            ->arg('$defaultLocale', $defaultLocale)
            ->arg('$filesPrefix', $production['files_prefix'])
            ->arg('$ordersPrefix', $production['orders_prefix'])
            ->public();

        if (!interface_exists(MessageBusInterface::class)) {
            return;
        }

        $services->set(ProducePrintFilesHandler::class)->autowire()
            ->tag('messenger.message_handler', ['handles' => ProducePrintFiles::class]);

        if ($production['on_purchase']) {
            $services->set(ProduceOnPurchase::class)->args([service('messenger.default_bus')])
                ->tag('kernel.event_listener', ['event' => OrderPurchased::class]);
        }
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
