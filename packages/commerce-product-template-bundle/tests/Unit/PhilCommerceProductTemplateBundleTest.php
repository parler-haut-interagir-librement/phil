<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
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
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryImageStore;
use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryProductionStore;
use Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProduceOnPurchase;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProducePrintFilesHandler;
use Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\PrintCompositor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceProductTemplateBundle::class)]
final class PhilCommerceProductTemplateBundleTest extends TestCase
{
    public function testItRegistersGenerationGroupsAndOptionsWithTheirDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_product_template', $this->extension()->getAlias());
        self::assertTrue($container->hasDefinition(VariantPlanner::class));

        $generation = $container->getDefinition(CatalogGeneration::class);
        self::assertTrue($generation->isAutowired());
        self::assertTrue($generation->isPublic());
        self::assertSame('en', $generation->getArgument('$defaultLocale'));

        self::assertTrue($container->getDefinition(ProductGroups::class)->isAutowired());
        self::assertTrue($container->getDefinition(ProductGroups::class)->isPublic());

        $options = $container->getDefinition(TemplateOptions::class);
        self::assertTrue($options->isAutowired());
        self::assertTrue($options->isAutoconfigured());
        self::assertFalse($options->isPublic());
        self::assertSame('product', $options->getArgument('$sellableType'));

        self::assertFalse($container->hasDefinition(Mockups::class));
        self::assertFalse($container->hasAlias(ImageStore::class));
    }

    public function testWithAnImagesDirectoryMockupsAreComposed(): void
    {
        $container = $this->load(['images_directory' => '/srv/images', 'default_locale' => 'fr', 'sellable_type' => 'shirt', 'mockups' => ['prefix' => 'shop/mockups', 'shading' => 0.5, 'quality' => 90]]);

        self::assertSame(['/srv/images'], $container->getDefinition(DirectoryImageStore::class)->getArguments());
        self::assertSame(DirectoryImageStore::class, (string) $container->getAlias(ImageStore::class));
        self::assertTrue($container->getAlias(ImageStore::class)->isPublic());
        self::assertTrue($container->hasDefinition(MockupCompositor::class));

        $mockups = $container->getDefinition(Mockups::class);
        self::assertTrue($mockups->isAutowired());
        self::assertTrue($mockups->isPublic());
        self::assertSame(['$prefix' => 'shop/mockups', '$shading' => 0.5, '$quality' => 90], $mockups->getArguments());
        self::assertSame('fr', $container->getDefinition(CatalogGeneration::class)->getArgument('$defaultLocale'));
        self::assertSame('shirt', $container->getDefinition(TemplateOptions::class)->getArgument('$sellableType'));
    }

    public function testTheMockupsHaveTheirDefaults(): void
    {
        $mockups = $this->load(['images_directory' => '/srv/images'])->getDefinition(Mockups::class);

        self::assertSame(['$prefix' => 'mockups', '$shading' => 0.8, '$quality' => 82], $mockups->getArguments());
    }

    public function testTheBoundsOfTheShadingAndQualityAreAccepted(): void
    {
        foreach ([[0.0, 0], [1.0, 100]] as [$shading, $quality]) {
            $mockups = $this->load(['images_directory' => '/srv/images', 'mockups' => ['shading' => $shading, 'quality' => $quality]])->getDefinition(Mockups::class);

            self::assertSame([$shading, $quality], [$mockups->getArgument('$shading'), $mockups->getArgument('$quality')]);
        }
    }

    public function testAShadingOrQualityOutOfRangeIsRefused(): void
    {
        $refused = 0;

        foreach ([['shading' => 1.1], ['shading' => -0.1], ['quality' => 101], ['quality' => -1], ['prefix' => '']] as $mockups) {
            try {
                $this->load(['mockups' => $mockups]);
            } catch (InvalidConfigurationException) {
                ++$refused;
            }
        }

        self::assertSame(5, $refused);
    }

    public function testWithAProductionDirectoryOrdersAreProducedInAWorker(): void
    {
        $container = $this->load(['images_directory' => '/srv/images', 'sellable_type' => 'shirt', 'default_locale' => 'fr', 'production' => [
            'directory' => '/srv/production',
            'files_prefix' => 'files',
            'orders_prefix' => 'orders',
            'fonts' => ['Sans' => '/srv/fonts/sans.ttf'],
        ]]);

        self::assertSame(['/srv/production'], $container->getDefinition(DirectoryProductionStore::class)->getArguments());
        self::assertSame(DirectoryProductionStore::class, (string) $container->getAlias(ProductionStore::class));
        self::assertTrue($container->getAlias(ProductionStore::class)->isPublic());
        self::assertSame([['Sans' => '/srv/fonts/sans.ttf']], $container->getDefinition(ConfiguredFonts::class)->getArguments());
        self::assertSame(ConfiguredFonts::class, (string) $container->getAlias(PrintFonts::class));
        self::assertTrue($container->hasDefinition(PrintCompositor::class));
        $production = $container->getDefinition(PrintProduction::class);
        self::assertTrue($production->isPublic());
        self::assertTrue($production->isAutowired());
        self::assertSame(['$sellableType' => 'shirt', '$defaultLocale' => 'fr', '$filesPrefix' => 'files', '$ordersPrefix' => 'orders'], $production->getArguments());
        $handler = $container->getDefinition(ProducePrintFilesHandler::class);
        self::assertTrue($handler->isAutowired());
        self::assertSame([['handles' => ProducePrintFiles::class]], $handler->getTag('messenger.message_handler'));
        self::assertFalse($container->hasDefinition(ProduceOnPurchase::class));
    }

    public function testTheProductionHasItsDefaults(): void
    {
        $container = $this->load(['images_directory' => '/srv/images', 'production' => ['directory' => '/srv/production']]);

        self::assertSame(['$sellableType' => 'product', '$defaultLocale' => 'en', '$filesPrefix' => 'print-files', '$ordersPrefix' => 'production-orders'], $container->getDefinition(PrintProduction::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ConfiguredFonts::class)->getArguments());
    }

    public function testOnPurchaseAPaidOrderAsksForItsProduction(): void
    {
        $listener = $this->load(['images_directory' => '/srv/images', 'production' => ['directory' => '/srv/production', 'on_purchase' => true]])->getDefinition(ProduceOnPurchase::class);

        self::assertEquals([new Reference('messenger.default_bus')], $listener->getArguments());
        self::assertSame([['event' => OrderPurchased::class]], $listener->getTag('kernel.event_listener'));
    }

    public function testWithoutAProductionDirectoryNothingIsProduced(): void
    {
        $container = $this->load(['images_directory' => '/srv/images', 'production' => ['on_purchase' => true]]);

        self::assertFalse($container->hasDefinition(PrintProduction::class));
        self::assertFalse($container->hasDefinition(ProducePrintFilesHandler::class));
        self::assertFalse($container->hasDefinition(ProduceOnPurchase::class));
        self::assertFalse($container->hasAlias(ProductionStore::class));
    }

    public function testAProductionWithoutTheStoreOfImagesIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('production.directory composes print files from the artworks of the store of images: set images_directory too.');

        $this->load(['production' => ['directory' => '/srv/production']]);
    }

    public function testEmptyPrefixesAndFontsAreRefused(): void
    {
        $refused = 0;

        foreach ([['files_prefix' => ''], ['orders_prefix' => ''], ['fonts' => ['Sans' => '']]] as $production) {
            try {
                $this->load(['images_directory' => '/srv/images', 'production' => ['directory' => '/srv/production'] + $production]);
            } catch (InvalidConfigurationException) {
                ++$refused;
            }
        }

        self::assertSame(3, $refused);
    }

    public function testWithAssetMapperTheControllerIsServed(): void
    {
        $container = $this->withCoreAndCatalogue();
        $container->registerExtension($this->extensionNamed('asset_mapper'));

        $this->prepending()->prepend($container);

        self::assertSame([['paths' => [PhilCommerceProductTemplateBundle::CONTROLLERS_DIR => '@phil/commerce-product-template-bundle']]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilCommerceProductTemplateBundle::CONTROLLERS_DIR.'/variant_selector_controller.js');
    }

    public function testWithDoctrineTemplatesDesignsAndGroupsAreStored(): void
    {
        $container = $this->load([]);

        foreach ([ProductTemplateRepository::class => DoctrineProductTemplateRepository::class, ProductDesignRepository::class => DoctrineProductDesignRepository::class, ProductGroupRepository::class => DoctrineProductGroupRepository::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
            $argument = $container->getDefinition($adapter)->getArgument(0);
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        }
    }

    public function testWithoutDoctrineOnlyThePlannerIsRegistered(): void
    {
        $container = $this->load(['images_directory' => '/srv/images'], []);

        self::assertTrue($container->hasDefinition(VariantPlanner::class));
        self::assertFalse($container->hasDefinition(CatalogGeneration::class));
        self::assertFalse($container->hasDefinition(Mockups::class));
        self::assertFalse($container->hasAlias(ProductGroupRepository::class));
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCoreAndCatalogue();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceProductTemplateBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceProductTemplateBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceProductTemplateBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = $this->withCoreAndCatalogue();
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testItRefusesToPrependWithoutTheCoreOrTheCatalogue(): void
    {
        $core = new ContainerBuilder();
        $core->registerExtension($this->extensionNamed('phil_commerce'));

        $catalogue = new ContainerBuilder();
        $catalogue->registerExtension($this->extensionNamed('phil_commerce_catalog'));

        foreach ([new ContainerBuilder(), $core, $catalogue] as $container) {
            try {
                $this->prepending()->prepend($container);
                self::fail('The module is refused.');
            } catch (LogicException $refused) {
                self::assertSame('PhilCommerceProductTemplateBundle is a module of the catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.', $refused->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $bundles
     */
    private function load(array $config, array $bundles = ['DoctrineBundle' => DoctrineBundle::class]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function withCoreAndCatalogue(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));
        $container->registerExtension($this->extensionNamed('phil_commerce_catalog'));

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommerceProductTemplateBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }

    private function prepending(): PrependExtensionInterface
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        return $extension;
    }

    private function extensionNamed(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
