<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\CommerceLogisticsBundle\Application\Contract\StockOwnership;
use Phil\CommerceLogisticsBundle\Application\ConventionStock;
use Phil\CommerceLogisticsBundle\Application\Costing;
use Phil\CommerceLogisticsBundle\Application\DeferredStock;
use Phil\CommerceLogisticsBundle\Application\DeliveryOptions;
use Phil\CommerceLogisticsBundle\Application\InventoryValue;
use Phil\CommerceLogisticsBundle\Application\LogisticsDocumentTypes;
use Phil\CommerceLogisticsBundle\Application\Port\CarrierGateway;
use Phil\CommerceLogisticsBundle\Application\Port\PrintOnDemand;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\Shipments;
use Phil\CommerceLogisticsBundle\Application\ShippingDocuments;
use Phil\CommerceLogisticsBundle\Application\ShippingLabels;
use Phil\CommerceLogisticsBundle\Application\ShippingProcessor;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Application\ShopUnitsFirst;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostingMethod;
use Phil\CommerceLogisticsBundle\Domain\Repository\DeliveryMethodRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\LocationRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingCostRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCarrier;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCredentials;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceLogisticsBundle::class)]
final class PhilCommerceLogisticsBundleTest extends TestCase
{
    public function testItWiresStockAndDelivery(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_logistics', $this->extension()->getAlias());
        self::assertTrue($container->getDefinition(Stock::class)->isPublic());
        self::assertTrue($container->getDefinition(Stock::class)->isAutowired());
        $dispatcher = $container->getDefinition(Stock::class)->getArgument('$dispatcher');
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame(['event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE], [(string) $dispatcher, $dispatcher->getInvalidBehavior()]);
        self::assertTrue($container->getDefinition(Shipments::class)->isPublic());
        self::assertTrue($container->getDefinition(Shipments::class)->isAutowired());
        self::assertTrue($container->getDefinition(DeliveryOptions::class)->isPublic());
        self::assertTrue($container->getDefinition(DeliveryOptions::class)->isAutowired());
        $processor = $container->getDefinition(ShippingProcessor::class);
        self::assertTrue($processor->isAutowired());
        self::assertSame([['after' => 'Phil\\CommerceCatalogBundle\\Application\\PromotionProcessor', 'before' => TaxOrderProcessor::class]], $processor->getTag('phil_commerce.order_processor'));
    }

    public function testItWiresPurchasingSerialsDocumentsWavesAndConventions(): void
    {
        $container = $this->load([]);

        foreach ([Purchasing::class, SerialNumbers::class, ShippingDocuments::class, ShippingWaves::class, ConventionStock::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        $conventions = $container->getDefinition(ConventionStock::class);
        self::assertSame([30, []], [$conventions->getArgument('$windowDays'), $conventions->getArgument('$onlineChannels')]);
        $printers = $conventions->getArgument('$printers');
        self::assertInstanceOf(TaggedIteratorArgument::class, $printers);
        self::assertSame('phil_commerce_logistics.print_on_demand', $printers->getTag());
        self::assertSame(['phil_commerce_logistics.print_on_demand' => [[]]], $container->getAutoconfiguredInstanceof()[PrintOnDemand::class]->getTags());

        $deferred = $container->getDefinition(DeferredStock::class);
        self::assertSame([[]], $deferred->getTag('phil_commerce.stock_commitments'));
        self::assertFalse($deferred->isPublic());

        $types = $container->getDefinition(LogisticsDocumentTypes::class);
        self::assertSame(['DN-', 'PS-', 8], $types->getArguments());
        self::assertSame([[]], $types->getTag('phil_commerce.document_types'));
    }

    public function testTheSeriesAndTheConventionWindowAreConfigured(): void
    {
        $container = $this->load(['documents' => ['delivery_note_prefix' => 'BL-', 'picking_slip_prefix' => 'BP-', 'digits' => 6], 'conventions' => ['window_days' => 14, 'online_channels' => ['web', 'app']]]);

        self::assertSame(['BL-', 'BP-', 6], $container->getDefinition(LogisticsDocumentTypes::class)->getArguments());
        self::assertSame([14, ['web', 'app']], [$container->getDefinition(ConventionStock::class)->getArgument('$windowDays'), $container->getDefinition(ConventionStock::class)->getArgument('$onlineChannels')]);
    }

    public function testTheConventionWindowIsBounded(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['conventions' => ['window_days' => 367]]);
    }

    public function testTheEdgesOfTheConfiguredNumbersAreTaken(): void
    {
        self::assertSame(1, $this->load(['documents' => ['digits' => 1]])->getDefinition(LogisticsDocumentTypes::class)->getArgument(2));
        self::assertSame(18, $this->load(['documents' => ['digits' => 18]])->getDefinition(LogisticsDocumentTypes::class)->getArgument(2));
        self::assertSame(0, $this->load(['conventions' => ['window_days' => 0]])->getDefinition(ConventionStock::class)->getArgument('$windowDays'));
        self::assertSame(366, $this->load(['conventions' => ['window_days' => 366]])->getDefinition(ConventionStock::class)->getArgument('$windowDays'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedNumbers(): iterable
    {
        yield 'no digit' => [['documents' => ['digits' => 0]]];
        yield 'nineteen digits' => [['documents' => ['digits' => 19]]];
        yield 'a window of minus one day' => [['conventions' => ['window_days' => -1]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedNumbers')]
    public function testTheNumbersBeyondTheirEdgesAreRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    public function testTheDocumentTypesAreDeclaredWithoutDoctrine(): void
    {
        $container = $this->load([], []);

        self::assertTrue($container->hasDefinition(LogisticsDocumentTypes::class));
        self::assertFalse($container->hasDefinition(Purchasing::class));
    }

    public function testItWiresTheCarriersWithoutAnyByDefault(): void
    {
        $container = $this->load([]);

        $labels = $container->getDefinition(ShippingLabels::class);
        self::assertTrue($labels->isPublic());
        self::assertTrue($labels->isAutowired());
        $carriers = $labels->getArgument('$carriers');
        self::assertInstanceOf(TaggedIteratorArgument::class, $carriers);
        self::assertSame('phil_commerce_logistics.carrier', $carriers->getTag());
        self::assertSame(['phil_commerce_logistics.carrier' => [[]]], $container->getAutoconfiguredInstanceof()[CarrierGateway::class]->getTags());
        self::assertSame(DoctrineLogisticsRepository::class, (string) $container->getAlias(ShippingCostRepository::class));
        self::assertFalse($container->hasDefinition(CanadaPostCarrier::class));
    }

    public function testCanadaPostIsWiredWhenConfigured(): void
    {
        $container = $this->load(['carriers' => ['canada_post' => [
            'api_key' => '%env(CANADA_POST_API_KEY)%',
            'api_secret' => '%env(CANADA_POST_API_SECRET)%',
            'customer_number' => '1234567',
            'contract_id' => '0042708517',
            'base_uri' => 'https://api.test.example',
            'token_uri' => 'https://auth.test.example/token',
            'language' => 'fr-CA',
        ]]], []);

        $carrier = $container->getDefinition(CanadaPostCarrier::class);
        self::assertSame([[]], $carrier->getTag('phil_commerce_logistics.carrier'));
        self::assertSame([['method' => 'reset']], $carrier->getTag('kernel.reset'));
        $client = $carrier->getArgument(0);
        self::assertInstanceOf(Reference::class, $client);
        self::assertSame('http_client', (string) $client);
        $credentials = $carrier->getArgument(1);
        self::assertInstanceOf(Definition::class, $credentials);
        self::assertSame(CanadaPostCredentials::class, $credentials->getClass());
        self::assertSame(['%env(CANADA_POST_API_KEY)%', '%env(CANADA_POST_API_SECRET)%', '1234567', 'https://api.test.example', 'https://auth.test.example/token', '0042708517', 'fr-CA'], $credentials->getArguments());
    }

    public function testCanadaPostAnswersInEnglishOrFrench(): void
    {
        $container = $this->load(['carriers' => ['canada_post' => ['enabled' => true, 'api_key' => 'k', 'api_secret' => 's', 'customer_number' => '1', 'base_uri' => 'https://a.example', 'token_uri' => 'https://a.example', 'language' => 'en-CA']]]);

        $credentials = $container->getDefinition(CanadaPostCarrier::class)->getArgument(1);
        self::assertInstanceOf(Definition::class, $credentials);
        self::assertSame('en-CA', $credentials->getArgument(6));

        $this->expectException(InvalidConfigurationException::class);
        $this->load(['carriers' => ['canada_post' => ['enabled' => true, 'api_key' => 'k', 'api_secret' => 's', 'customer_number' => '1', 'base_uri' => 'https://a.example', 'token_uri' => 'https://a.example', 'language' => 'de-DE']]]);
    }

    public function testCanadaPostRefusesAnIncompleteConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Canada Post needs api_key, api_secret, customer_number, base_uri and token_uri.');

        $this->load(['carriers' => ['canada_post' => ['api_key' => 'k', 'api_secret' => 's', 'customer_number' => '1', 'base_uri' => 'https://a.example']]]);
    }

    public function testCanadaPostCanBeConfiguredAndLeftOff(): void
    {
        self::assertFalse($this->load(['carriers' => ['canada_post' => ['enabled' => false, 'api_key' => 'k']]])->hasDefinition(CanadaPostCarrier::class));
    }

    public function testCostingIsOffByDefaultAndItsValuationReadsNothingYet(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(Costing::class));
        self::assertFalse($container->hasAlias(StockOwnership::class));
        self::assertSame(InventoryValue::class, (string) $container->getAlias(InventoryValuation::class));
        self::assertTrue($container->getAlias(InventoryValuation::class)->isPublic());
        self::assertTrue($container->getDefinition(InventoryValue::class)->isAutowired());

        foreach ([Stock::class, Purchasing::class] as $service) {
            $costing = $container->getDefinition($service)->getArgument('$costing');
            self::assertInstanceOf(Reference::class, $costing);
            self::assertSame([Costing::class, ContainerInterface::NULL_ON_INVALID_REFERENCE], [(string) $costing, $costing->getInvalidBehavior()], $service);
        }
    }

    public function testCostingIsWiredWithItsMethodItsCurrencyAndTheShopsUnitsFirst(): void
    {
        $container = $this->load(['costing' => ['method' => 'fifo', 'currency' => 'CAD']]);
        $arguments = $container->getDefinition(Costing::class)->getArguments();

        self::assertSame(ShopUnitsFirst::class, (string) $container->getAlias(StockOwnership::class));
        self::assertTrue($container->hasDefinition(ShopUnitsFirst::class));
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[3]);
        self::assertSame([DoctrineLogisticsRepository::class, StockOwnership::class], [(string) $arguments[0], (string) $arguments[3]]);
        self::assertSame(CostingMethod::FirstInFirstOut, $arguments[1]);
        self::assertInstanceOf(Definition::class, $arguments[2]);
        self::assertSame([[Currency::class, 'of'], ['CAD']], [$arguments[2]->getFactory(), $arguments[2]->getArguments()]);
        self::assertSame(CostingMethod::WeightedAverage, $this->load(['costing' => ['currency' => 'EUR']])->getDefinition(Costing::class)->getArgument(1));
        self::assertSame(CostingMethod::WeightedAverage, $this->load(['costing' => ['enabled' => true, 'currency' => 'EUR']])->getDefinition(Costing::class)->getArgument(1));
        self::assertFalse($this->load(['costing' => ['enabled' => false, 'currency' => 'EUR']])->hasDefinition(Costing::class));
        self::assertFalse($this->load(['costing' => ['enabled' => false]])->hasDefinition(Costing::class));
    }

    /**
     * @param array<string, mixed> $costing
     */
    #[DataProvider('incompleteCosting')]
    public function testCostingNeedsTheCodeOfItsCurrency(array $costing): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Costing needs the ISO 4217 code of its currency, three capital letters.');

        $this->load(['costing' => $costing]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function incompleteCosting(): iterable
    {
        yield 'no currency' => [['enabled' => true]];
        yield 'small letters' => [['currency' => 'cad']];
        yield 'too long' => [['currency' => 'CADX']];
        yield 'a line after it' => [['currency' => "CAD\n"]];
        yield 'too short' => [['currency' => 'CA']];
    }

    public function testCostingKnowsItsMethodsOnly(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['costing' => ['method' => 'lifo', 'currency' => 'CAD']]);
    }

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        foreach ([LocationRepository::class => DoctrineLogisticsRepository::class, StockRepository::class => DoctrineLogisticsRepository::class, DeliveryMethodRepository::class => DoctrineLogisticsRepository::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
            $argument = $container->getDefinition($adapter)->getArgument(0);
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        }
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceLogisticsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceLogisticsBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceLogisticsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(Stock::class));
        self::assertFalse($container->hasDefinition(Shipments::class));
        self::assertFalse($container->hasAlias(StockRepository::class));
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceLogisticsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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

    private function withCore(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommerceLogisticsBundle()->getContainerExtension();
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
