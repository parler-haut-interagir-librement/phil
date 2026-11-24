<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle;

use LogicException;
use Override;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceCatalogBundle\Application\CommunityCopies;
use Phil\CommerceCatalogBundle\Application\CompositeCosts;
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Application\Contract\CommunityCopyLedger;
use Phil\CommerceCatalogBundle\Application\Contract\ProductClassification;
use Phil\CommerceCatalogBundle\Application\Contract\ProductCosts;
use Phil\CommerceCatalogBundle\Application\Contract\ProductListing;
use Phil\CommerceCatalogBundle\Application\Contract\UnitCosts;
use Phil\CommerceCatalogBundle\Application\PriceBookPrices;
use Phil\CommerceCatalogBundle\Application\PriceRuleSellables;
use Phil\CommerceCatalogBundle\Application\ProductAvailability;
use Phil\CommerceCatalogBundle\Application\ProductSellables;
use Phil\CommerceCatalogBundle\Application\ProductTexts;
use Phil\CommerceCatalogBundle\Application\ProductVariants;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DbalProductListing;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrineCommunityCopyLedger;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrinePriceBookRepository;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\DoctrineProductRepository;
use Phil\CommerceCatalogBundle\Infrastructure\Http\ProductFeedController;
use Phil\CommerceCatalogBundle\Infrastructure\Logistics\LogisticsUnitCosts;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\FeedDefinition;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\ProductFeeds;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeed;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeedCommand;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeedHandler;
use Phil\CommerceCatalogBundle\Infrastructure\Taxonomy\TaxonomyProductClassification;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\ProductFeeds\FeedPublisher;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The catalogue, a module of the core of commerce: products — physical, digital or services — with their attributes,
 * their families and variants, and the composite products made of them, sold through the core's `SellableResolver`
 * under the reference type `product`; price books per customer group, channel, period and quantity tier, through the
 * core's `UnitPriceResolver`; and the channels' selections, through its `SellableAvailability`. Product names are
 * translated with `phil/doctrine-behaviours`; products are filed in the categories and tags of
 * `phil/taxonomy-bundle` when it registers its taxonomy.
 *
 * Price rules sell a product at the price the buyer chose, under the reference type `pay_what_you_want` with the
 * core's amount option, and community copies, under `community_copy`, funded by paid orders through the core's
 * `OrderPurchased`.
 *
 * The cost of a product, a composite's the sum of its parts', is read through `ProductCosts`, from the costing of
 * `phil/commerce-logistics-bundle` when the application registers it.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. With DoctrineBundle, products, their
 * translations, price books and community copies are mapped and stored. Promotions are the module `phil/commerce-promotions-bundle`.
 *
 * ```yaml
 * phil_commerce_catalog:
 *     pay_what_you_want: { default_maximum: 50000 }
 *     translations:
 *         locales: { fr_CA: [fr], fr: [en], en: [] }
 * ```
 */
final class PhilCommerceCatalogBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_catalog';

    /**
     * The products are filed in `phil/taxonomy-bundle` when it registers its taxonomy.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasDefinition(TaxonomyProductClassification::TAXONOMY) && !$container->hasAlias(TaxonomyProductClassification::TAXONOMY)) {
                    $container->removeDefinition(TaxonomyProductClassification::class);
                    $container->removeAlias(ProductClassification::class);
                }
            }
        });
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('translations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('locales')
                            ->info('The locales the products are shown in, each with the locales tried after it: {fr_CA: [fr], fr: [en], en: []}. None: every product in its own name.')
                            ->useAttributeAsKey('locale')
                            ->normalizeKeys(false)
                            ->arrayPrototype()->scalarPrototype()->cannotBeEmpty()->end()->end()
                            ->defaultValue([])
                            ->validate()
                                ->always(static function (array $locales): array {
                                    try {
                                        DeclaredLocales::fromArray($locales);
                                    } catch (InvalidLocaleDimension $invalidLocaleDimension) {
                                        throw new InvalidConfigurationException('phil_commerce_catalog.translations.locales: '.$invalidLocaleDimension->getMessage(), 0, $invalidLocaleDimension);
                                    }

                                    return $locales;
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pay_what_you_want')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('default_maximum')
                            ->info("The highest price a buyer may choose for a product without a maximum of its own, in minor units of the product's currency; none: such a product is not sold at a chosen price.")
                            ->min(1)
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('product_feeds')
                    ->info('The product feeds, by name, each written to a file by phil/product-feeds: {google: {path: "%kernel.project_dir%/public/feeds/google.xml", title: "The Dice Shop", link: "https://shop.example/", description: "Dice and games.", product_link: "https://shop.example/products/{sku}"}}.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->info('Where the file is served from, in an existing, writable directory.')->isRequired()->cannotBeEmpty()->end()
                            ->enumNode('format')->values(['xml', 'tsv'])->defaultValue('xml')->end()
                            ->scalarNode('title')->info("The store's name, written in the RSS channel.")->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('link')->info("The store's address.")->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('description')->info('A sentence that describes the store.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('channel')->info('The channel whose selection is published; none publishes what every channel sells, and prices without a channel.')->defaultNull()->end()
                            ->scalarNode('locale')->info("The locale of the names, along the chain of translations.locales; none publishes each product's own name.")->defaultNull()->end()
                            ->scalarNode('product_link')->info("The address of a product's page: {sku}, {family}.")->isRequired()->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (mixed $link): bool => !\is_string($link) || (!str_contains($link, '{sku}') && !str_contains($link, '{family}')))
                                    ->thenInvalid('The product link %s names {sku} or {family}.')
                                ->end()
                            ->end()
                            ->scalarNode('image_link')->info('The address of a product\'s image: {sku}, {family}; an "image" attribute holding an address wins.')->defaultNull()->end()
                            ->arrayNode('attributes')
                                ->info('The attribute, or variant axis, each field reads when not its own name: {color: colour, gtin: ean}.')
                                ->useAttributeAsKey('field')
                                ->normalizeKeys(false)
                                ->scalarPrototype()->cannotBeEmpty()->end()
                                ->validate()
                                    ->ifTrue(static fn (array $attributes): bool => [] !== array_diff(array_keys($attributes), FeedDefinition::FIELDS))
                                    ->thenInvalid('The fields a feed reads are '.implode(', ', FeedDefinition::FIELDS).'; %s names another.')
                                ->end()
                            ->end()
                            ->booleanNode('allow_localhost')->info('Whether the addresses may be on localhost, in development and tests.')->defaultFalse()->end()
                            ->integerNode('max_age')->info('How long a cache keeps the file the route of config/routes.php serves, in seconds.')->min(0)->defaultValue(FeedDefinition::DEFAULT_MAX_AGE)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension;
     * each extension then loads in a container of its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce')) {
            throw new LogicException('PhilCommerceCatalogBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceCatalogBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceCatalogBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Every service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        /** @var array{translations: array{locales: array<string, list<string>>}, pay_what_you_want: array{default_maximum: ?int}, product_feeds: array<string, array{path: string, format: string, title: string, link: string, description: string, channel: ?string, locale: ?string, product_link: string, image_link: ?string, attributes: array<string, string>, allow_localhost: bool, max_age: int}>} $config */
        $locales = $config['translations']['locales'];

        if ([] === $locales) {
            $services->set(ProductTexts::class)->public();
        } else {
            $services->set('phil_commerce_catalog.translations', Translations::class)->args([new Reference('doctrine.orm.entity_manager')]);
            $services->set('phil_commerce_catalog.locales', DeclaredLocales::class)->factory([DeclaredLocales::class, 'fromArray'])->args([$locales]);
            $services->set(ProductTexts::class)
                ->args([new Reference('phil_commerce_catalog.translations'), new Reference('phil_commerce_catalog.locales'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }

        // Tagged by the core's autoconfiguration: sellable resolvers of the types `product`, `pay_what_you_want` and
        // `community_copy`, the options of `pay_what_you_want`, a unit price resolver, the channels' selections.
        $services->set(ProductSellables::class)->args([new Reference(ProductRepository::class), new Reference(ProductTexts::class)])->autoconfigure();
        // The price rules: a price the buyer chose, a community copy.
        $services->set(PriceRuleSellables::class)
            ->args([new Reference(ProductRepository::class), new Reference(ProductTexts::class), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['pay_what_you_want']['default_maximum']])
            ->autoconfigure();
        // Funded by each order the core says was paid in full.
        $services->set(CommunityCopies::class)
            ->args([new Reference(ProductRepository::class), new Reference(CommunityCopyLedger::class), new Reference(OrderRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->tag('kernel.event_listener', ['event' => OrderPurchased::class, 'method' => '__invoke'])
            ->public();
        $services->set(PriceBookPrices::class)->autowire()->autoconfigure()->tag('kernel.reset', ['method' => 'reset']);
        $services->set(ProductAvailability::class)->autowire()->autoconfigure();
        $services->set(ProductVariants::class)->autowire()->public();
        $services->set(CompositeProducts::class)->autowire()->public();
        // The cost of a product, a composite's the sum of its parts': from the logistics module's costing when the
        // application registers it, or from the application's own `UnitCosts`; unknown without either.
        $services->set(CompositeCosts::class)->args([new Reference(ProductRepository::class), new Reference(UnitCosts::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $services->alias(ProductCosts::class, CompositeCosts::class)->public();

        if ($this->hasBundle($builder, 'PhilCommerceLogisticsBundle')) {
            $services->set(LogisticsUnitCosts::class)->args([new Reference(InventoryValuation::class)]);
            $services->alias(UnitCosts::class, LogisticsUnitCosts::class);
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineProductRepository::class, DoctrinePriceBookRepository::class, DoctrineCommunityCopyLedger::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        $services->set(DbalProductListing::class)
            ->args([new Reference('doctrine.dbal.default_connection'), [] === $locales ? null : new Reference('phil_commerce_catalog.locales')])
            ->public();
        $services->alias(ProductListing::class, DbalProductListing::class)->public();

        $this->loadProductFeeds($config['product_feeds'], $container);

        if (class_exists(TaxonomyProductClassification::TAXONOMY)) {
            $services->set(TaxonomyProductClassification::class)->args([new Reference(TaxonomyProductClassification::TAXONOMY), new Reference(TaxonomyRepository::class)]);
            $services->alias(ProductClassification::class, TaxonomyProductClassification::class)->public();
        }
    }

    /**
     * The feeds of phil/product-feeds, when it is installed: one definition per feed, the service that publishes them,
     * the command, the handler of Symfony Messenger when it is installed too, and the controller of the route that
     * serves the files.
     *
     * @param array<string, array{path: string, format: string, title: string, link: string, description: string, channel: ?string, locale: ?string, product_link: string, image_link: ?string, attributes: array<string, string>, allow_localhost: bool, max_age: int}> $feeds
     */
    private function loadProductFeeds(array $feeds, ContainerConfigurator $container): void
    {
        if (!interface_exists(ProductSource::class)) {
            if ([] !== $feeds) {
                throw new LogicException('phil_commerce_catalog.product_feeds publishes the catalogue through phil/product-feeds: require it.');
            }

            return;
        }

        $services = $container->services();
        $definitions = [];

        foreach ($feeds as $name => $feed) {
            // The factories are named by strings: a closure cannot be dumped into the compiled container.
            $definitions[] = new Definition(FeedDefinition::class, [
                $name,
                $feed['path'],
                new Definition(FeedFormat::class, [$feed['format']])->setFactory(FeedFormat::class.'::from'),
                new Definition(FeedChannel::class, [
                    $feed['title'],
                    new Definition(Url::class, [$feed['link'], $feed['allow_localhost']])->setFactory(Url::class.'::fromString'),
                    $feed['description'],
                ]),
                $feed['product_link'],
                $feed['image_link'],
                $feed['channel'],
                $feed['locale'],
                $feed['attributes'],
                $feed['allow_localhost'],
                $feed['max_age'],
            ]);
        }

        $services->set(FeedPublisher::class);
        $services->set(ProductFeeds::class)
            ->args([
                $definitions,
                new Reference(ProductListing::class),
                new Reference(ClockInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(FeedPublisher::class),
                new Reference(PriceBookPrices::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();
        $services->set(PublishProductFeedHandler::class)->args([new Reference(ProductFeeds::class)]);

        if (interface_exists(MessageBusInterface::class)) {
            $services->get(PublishProductFeedHandler::class)->tag('messenger.message_handler', ['handles' => PublishProductFeed::class]);
        }

        if (class_exists(Command::class)) {
            $services->set(PublishProductFeedCommand::class)->args([new Reference(ProductFeeds::class)])->tag('console.command');
        }

        if (class_exists(BinaryFileResponse::class)) {
            $services->set(ProductFeedController::class)->args([new Reference(ProductFeeds::class)])->tag('controller.service_arguments')->public();
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
