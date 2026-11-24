<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
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
use Phil\CommerceCatalogBundle\Domain\Repository\PriceBookRepository;
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
use Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\ProductFeeds\FeedPublisher;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceCatalogBundle::class)]
final class PhilCommerceCatalogBundleTest extends TestCase
{
    public function testItPlugsItsResolversIntoTheCore(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_catalog', $this->extension()->getAlias());

        foreach ([PriceBookPrices::class, ProductAvailability::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isAutoconfigured());
            self::assertTrue($container->getDefinition($service)->isAutowired());
            self::assertFalse($container->getDefinition($service)->isPublic());
        }

        $sellables = $container->getDefinition(ProductSellables::class);
        self::assertTrue($sellables->isAutoconfigured());
        self::assertFalse($sellables->isPublic());
        self::assertSame([ProductRepository::class, ProductTexts::class], $this->ids($sellables->getArguments()));
        self::assertSame([['method' => 'reset']], $container->getDefinition(PriceBookPrices::class)->getTag('kernel.reset'));

        foreach ([ProductVariants::class, CompositeProducts::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isAutowired());
            self::assertTrue($container->getDefinition($service)->isPublic());
        }

        $rules = $container->getDefinition(PriceRuleSellables::class);
        self::assertTrue($rules->isAutoconfigured());
        self::assertFalse($rules->isPublic());
        self::assertSame([ProductRepository::class, ProductTexts::class, 'translator', 'null'], $this->ids($rules->getArguments()));
        self::assertInstanceOf(Reference::class, $rules->getArgument(2));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $rules->getArgument(2)->getInvalidBehavior());
        self::assertNull($rules->getArgument(3));

        $copies = $container->getDefinition(CommunityCopies::class);
        self::assertTrue($copies->isPublic());
        self::assertSame([ProductRepository::class, CommunityCopyLedger::class, OrderRepository::class], $this->ids($copies->getArguments()));
        self::assertInstanceOf(Reference::class, $copies->getArgument(2));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $copies->getArgument(2)->getInvalidBehavior());
        self::assertSame([['event' => OrderPurchased::class, 'method' => '__invoke']], $copies->getTag('kernel.event_listener'));
        self::assertSame(DoctrineCommunityCopyLedger::class, (string) $container->getAlias(CommunityCopyLedger::class));
        self::assertTrue($container->getAlias(CommunityCopyLedger::class)->isPublic());
    }

    public function testTheCostOfProductsIsReadFromTheApplicationsUnitCostsWithoutTheLogistics(): void
    {
        $container = $this->load([]);
        $arguments = $container->getDefinition(CompositeCosts::class)->getArguments();

        self::assertSame(CompositeCosts::class, (string) $container->getAlias(ProductCosts::class));
        self::assertTrue($container->getAlias(ProductCosts::class)->isPublic());
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame([ProductRepository::class, UnitCosts::class, ContainerInterface::NULL_ON_INVALID_REFERENCE], [(string) $arguments[0], (string) $arguments[1], $arguments[1]->getInvalidBehavior()]);
        self::assertFalse($container->hasDefinition(LogisticsUnitCosts::class));
        self::assertFalse($container->hasAlias(UnitCosts::class));
    }

    public function testTheCostOfProductsIsReadFromTheLogisticsWhenItIsRegistered(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class, 'PhilCommerceLogisticsBundle' => PhilCommerceLogisticsBundle::class]);
        $valuation = $container->getDefinition(LogisticsUnitCosts::class)->getArgument(0);

        self::assertSame(LogisticsUnitCosts::class, (string) $container->getAlias(UnitCosts::class));
        self::assertInstanceOf(Reference::class, $valuation);
        self::assertSame([InventoryValuation::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE], [(string) $valuation, $valuation->getInvalidBehavior()]);
    }

    public function testTheSiteGivesTheDefaultMaximumOfAChosenPrice(): void
    {
        self::assertSame(50_000, $this->load(['pay_what_you_want' => ['default_maximum' => 50_000]])->getDefinition(PriceRuleSellables::class)->getArgument(3));
        self::assertSame(1, $this->load(['pay_what_you_want' => ['default_maximum' => 1]])->getDefinition(PriceRuleSellables::class)->getArgument(3));
    }

    public function testADefaultMaximumIsAboveZero(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('default_maximum');

        $this->load(['pay_what_you_want' => ['default_maximum' => 0]]);
    }

    public function testWithoutLocalesProductsKeepTheirOwnNames(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getDefinition(ProductTexts::class)->getArguments());
        self::assertTrue($container->getDefinition(ProductTexts::class)->isPublic());
        self::assertFalse($container->hasDefinition('phil_commerce_catalog.translations'));
    }

    public function testWithLocalesProductNamesAreTranslated(): void
    {
        $container = $this->load(['translations' => ['locales' => ['fr_CA' => ['fr'], 'fr' => ['en'], 'en' => []]]]);

        $texts = $container->getDefinition(ProductTexts::class);
        self::assertTrue($texts->isPublic());
        $arguments = $texts->getArguments();
        self::assertSame(['phil_commerce_catalog.translations', 'phil_commerce_catalog.locales', 'translator'], $this->ids($arguments));
        self::assertInstanceOf(Reference::class, $arguments[2]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());
        self::assertSame(Translations::class, $container->getDefinition('phil_commerce_catalog.translations')->getClass());
        self::assertSame('doctrine.orm.entity_manager', $this->ids([$container->getDefinition('phil_commerce_catalog.translations')->getArgument(0)])[0]);
        $locales = $container->getDefinition('phil_commerce_catalog.locales');
        self::assertSame([DeclaredLocales::class, 'fromArray'], $locales->getFactory());
        self::assertSame([['fr_CA' => ['fr'], 'fr' => ['en'], 'en' => []]], $locales->getArguments());
    }

    public function testAnUnknownLocaleIsRefused(): void
    {
        try {
            $this->load(['translations' => ['locales' => ['fr' => ['xx-not a locale']]]]);
            self::fail('The locales were accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            $previous = $invalidConfigurationException->getPrevious();

            self::assertInstanceOf(InvalidLocaleDimension::class, $previous);
            self::assertSame('phil_commerce_catalog.translations.locales: '.$previous->getMessage(), $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testTheLocaleKeysAreNotNormalized(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"fr-CA" is not a locale');

        $this->load(['translations' => ['locales' => ['fr-CA' => []]]]);
    }

    public function testTheTaxonomyFilesProductsWhenItIsRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(TaxonomyProductClassification::class, (string) $container->getAlias(ProductClassification::class));
        self::assertTrue($container->getAlias(ProductClassification::class)->isPublic());
        self::assertSame([Taxonomy::class, TaxonomyRepository::class], $this->ids($container->getDefinition(TaxonomyProductClassification::class)->getArguments()));
    }

    public function testWithoutTheTaxonomyServiceNoProductIsFiled(): void
    {
        $container = $this->built(static function (ContainerBuilder $container): void {
        });

        self::assertFalse($container->hasDefinition(TaxonomyProductClassification::class));
        self::assertFalse($container->hasAlias(ProductClassification::class));
        self::assertTrue($container->hasDefinition(ProductSellables::class));
    }

    public function testTheTaxonomyServiceOrItsAliasKeepsTheFiling(): void
    {
        foreach ([
            static fn (ContainerBuilder $container): mixed => $container->register(Taxonomy::class),
            static fn (ContainerBuilder $container): mixed => $container->setAlias(Taxonomy::class, 'some.taxonomy'),
        ] as $taxonomy) {
            $container = $this->built($taxonomy);

            self::assertTrue($container->hasDefinition(TaxonomyProductClassification::class));
            self::assertTrue($container->hasAlias(ProductClassification::class));
        }
    }

    public function testWithDoctrineProductsAndPriceBooksAreStored(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        foreach ([ProductRepository::class => DoctrineProductRepository::class, PriceBookRepository::class => DoctrinePriceBookRepository::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
            $argument = $container->getDefinition($adapter)->getArgument(0);
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        }
    }

    public function testWithDoctrineProductsAreListedOnDbal(): void
    {
        $container = $this->load([]);
        $listing = $container->getDefinition(DbalProductListing::class);

        self::assertTrue($listing->isPublic());
        self::assertSame(['doctrine.dbal.default_connection'], $this->ids([$listing->getArgument(0)]));
        self::assertNull($listing->getArgument(1));
        self::assertSame(DbalProductListing::class, (string) $container->getAlias(ProductListing::class));
        self::assertTrue($container->getAlias(ProductListing::class)->isPublic());

        $translated = $this->load(['translations' => ['locales' => ['fr' => []]]])->getDefinition(DbalProductListing::class);
        self::assertSame(['doctrine.dbal.default_connection', 'phil_commerce_catalog.locales'], $this->ids($translated->getArguments()));
    }

    public function testTheFeedsArePublishedByAServiceACommandAndAMessage(): void
    {
        $container = $this->load(['product_feeds' => [
            'google' => [
                'path' => '/srv/public/google.xml',
                'title' => 'The Dice Shop',
                'link' => 'https://shop.example/',
                'description' => 'Dice and games.',
                'product_link' => 'https://shop.example/products/{sku}',
            ],
            'Meta' => [
                'path' => '/srv/public/meta.tsv',
                'format' => 'tsv',
                'title' => 'The Dice Shop',
                'link' => 'http://shop.localhost/',
                'description' => 'Dice and games.',
                'channel' => 'web',
                'locale' => 'fr',
                'product_link' => 'http://shop.localhost/{family}',
                'image_link' => 'http://shop.localhost/{sku}.jpg',
                'attributes' => ['color' => 'colour'],
                'allow_localhost' => true,
                'max_age' => 600,
            ],
        ]]);
        $feeds = $container->getDefinition(ProductFeeds::class);

        self::assertTrue($feeds->isPublic());
        self::assertSame([ProductListing::class, ClockInterface::class, FeedPublisher::class, PriceBookPrices::class, 'logger'], $this->ids(\array_slice($feeds->getArguments(), 1)));
        self::assertTrue($container->hasDefinition(FeedPublisher::class));

        foreach ([2, 5] as $optional) {
            $reference = $feeds->getArgument($optional);
            self::assertInstanceOf(Reference::class, $reference);
            self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $reference->getInvalidBehavior());
        }

        $definitions = $feeds->getArgument(0);
        self::assertIsArray($definitions);
        self::assertCount(2, $definitions);
        [$google, $meta] = $definitions;
        self::assertInstanceOf(Definition::class, $google);
        self::assertInstanceOf(Definition::class, $meta);
        self::assertSame(FeedDefinition::class, $google->getClass());
        self::assertSame(['google', '/srv/public/google.xml'], \array_slice($google->getArguments(), 0, 2));
        self::assertSame(['https://shop.example/products/{sku}', null, null, null, [], false, 3600], \array_slice($google->getArguments(), 4));
        self::assertSame(['Meta', '/srv/public/meta.tsv'], \array_slice($meta->getArguments(), 0, 2));
        self::assertSame(['http://shop.localhost/{family}', 'http://shop.localhost/{sku}.jpg', 'web', 'fr', ['color' => 'colour'], true, 600], \array_slice($meta->getArguments(), 4));

        $format = $meta->getArgument(2);
        self::assertInstanceOf(Definition::class, $format);
        self::assertSame([FeedFormat::class, 'from'], $format->getFactory());
        self::assertSame(['tsv'], $format->getArguments());
        $channel = $meta->getArgument(3);
        self::assertInstanceOf(Definition::class, $channel);
        self::assertSame(FeedChannel::class, $channel->getClass());
        self::assertSame('The Dice Shop', $channel->getArgument(0));
        self::assertSame('Dice and games.', $channel->getArgument(2));
        $link = $channel->getArgument(1);
        self::assertInstanceOf(Definition::class, $link);
        self::assertSame([Url::class, 'fromString'], $link->getFactory());
        self::assertSame(['http://shop.localhost/', true], $link->getArguments());
        $googleChannel = $google->getArgument(3);
        self::assertInstanceOf(Definition::class, $googleChannel);
        $googleLink = $googleChannel->getArgument(1);
        self::assertInstanceOf(Definition::class, $googleLink);
        self::assertSame(['https://shop.example/', false], $googleLink->getArguments());
        $googleFormat = $google->getArgument(2);
        self::assertInstanceOf(Definition::class, $googleFormat);
        self::assertSame(['xml'], $googleFormat->getArguments());

        $handler = $container->getDefinition(PublishProductFeedHandler::class);
        self::assertSame([ProductFeeds::class], $this->ids($handler->getArguments()));
        self::assertSame([['handles' => PublishProductFeed::class]], $handler->getTag('messenger.message_handler'));
        $command = $container->getDefinition(PublishProductFeedCommand::class);
        self::assertSame([ProductFeeds::class], $this->ids($command->getArguments()));
        self::assertSame([[]], $command->getTag('console.command'));
        $controller = $container->getDefinition(ProductFeedController::class);
        self::assertSame([ProductFeeds::class], $this->ids($controller->getArguments()));
        self::assertSame([[]], $controller->getTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());
    }

    public function testAFeedKeepsItsNameAsWrittenAndMayNeverBeCached(): void
    {
        $feed = ['path' => '/srv/google.xml', 'format' => 'xml', 'title' => 'Shop', 'link' => 'https://shop.example/', 'description' => 'Shop', 'product_link' => 'https://shop.example/{sku}', 'max_age' => 0];
        $definitions = $this->load(['product_feeds' => ['google-shopping' => $feed]])->getDefinition(ProductFeeds::class)->getArgument(0);

        self::assertIsArray($definitions);
        $definition = $definitions[0];
        self::assertInstanceOf(Definition::class, $definition);
        self::assertSame(['google-shopping', '/srv/google.xml'], \array_slice($definition->getArguments(), 0, 2));
        self::assertSame(0, $definition->getArgument(10));
    }

    public function testTheFieldsOfAFeedAreNotRenamed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The fields a feed reads are');

        $this->load(['product_feeds' => ['google' => ['path' => '/srv/google.xml', 'title' => 'Shop', 'link' => 'https://shop.example/', 'description' => 'Shop', 'product_link' => 'https://shop.example/{sku}', 'attributes' => ['product-type' => 'kind']]]]);
    }

    public function testAFeedIsKeptZeroSecondsOrMore(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('max_age');

        $this->load(['product_feeds' => ['google' => ['path' => '/srv/google.xml', 'title' => 'Shop', 'link' => 'https://shop.example/', 'description' => 'Shop', 'product_link' => 'https://shop.example/{sku}', 'max_age' => -1]]]);
    }

    public function testWithoutFeedsTheServiceListsNone(): void
    {
        $definitions = $this->load([])->getDefinition(ProductFeeds::class)->getArgument(0);

        self::assertSame([], $definitions);
    }

    public function testAProductLinkNamesTheProduct(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The product link "https:\/\/shop.example\/products" names {sku} or {family}.');

        $this->load(['product_feeds' => ['google' => ['path' => '/srv/google.xml', 'title' => 'Shop', 'link' => 'https://shop.example/', 'description' => 'Shop', 'product_link' => 'https://shop.example/products']]]);
    }

    public function testAFeedReadsKnownFieldsOnly(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The fields a feed reads are description, image, brand, gtin, mpn, condition, availability, color, size, material, pattern, product_type;');

        $this->load(['product_feeds' => ['google' => ['path' => '/srv/google.xml', 'title' => 'Shop', 'link' => 'https://shop.example/', 'description' => 'Shop', 'product_link' => 'https://shop.example/{sku}', 'attributes' => ['colour' => 'colour']]]]);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceCatalogBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceCatalogBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceCatalogBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(ProductSellables::class));
        self::assertFalse($container->hasDefinition(ProductTexts::class));
        self::assertFalse($container->hasAlias(ProductRepository::class));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testItRefusesToPrependWithoutTheCore(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilCommerceCatalogBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

        $this->prepending()->prepend(new ContainerBuilder());
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $bundles
     */
    private function load(array $config, array $bundles = ['DoctrineBundle' => DoctrineBundle::class]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load([$config], $container);

        return $container;
    }

    /**
     * The extension loaded and the bundle's own compiler passes run, once the taxonomy is registered or not.
     *
     * @param callable(ContainerBuilder): mixed $taxonomy
     */
    private function built(callable $taxonomy): ContainerBuilder
    {
        $container = $this->load([]);
        $passes = new ContainerBuilder();
        new PhilCommerceCatalogBundle()->build($passes);
        $taxonomy($container);

        foreach ($passes->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if (new ReflectionClass($pass)->isAnonymous()) {
                $pass->process($container);
            }
        }

        return $container;
    }

    private function withCore(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));

        return $container;
    }

    /**
     * The services and values a definition's arguments name.
     *
     * @param array<array-key, mixed> $arguments
     *
     * @return list<string>
     */
    private function ids(array $arguments): array
    {
        return array_values(array_map(static fn (mixed $argument): string => $argument instanceof Reference || \is_string($argument) ? (string) $argument : get_debug_type($argument), $arguments));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommerceCatalogBundle()->getContainerExtension();
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
