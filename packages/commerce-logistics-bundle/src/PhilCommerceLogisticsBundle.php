<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle;

use LogicException;
use Override;
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
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCarrier;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCredentials;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use Phil\Money\Currency;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Logistics, a module of the core of commerce: stock per SKU and location with its journal, receipts of goods,
 * counts, transfers and the reservations of placed orders; and delivery methods per zone — shipping, local delivery,
 * pickup — whose cost the core's `OrderProcessor` adds to the order; and the carriers' rates and labels, with what
 * each shipment really cost beside what the buyer paid, Canada Post's adapter wired when it is configured. Backorders
 * per SKU, the stock of convention items and print on demand, purchase orders, serial numbers, delivery notes and
 * picking slips in the core's register, shipping waves, and the stock of the core's deferred sales. With costing
 * enabled, the value of the stock at cost — weighted average or first in, first out —, landed costs, variances by
 * reason, and customer returns at the cost they left at.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. With DoctrineBundle, all of it is
 * mapped and stored.
 */
final class PhilCommerceLogisticsBundle extends AbstractBundle
{
    /** The catalogue's order processor for promotions, which the shipping processor runs after. */
    private const string PROMOTION_PROCESSOR = 'Phil\\CommerceCatalogBundle\\Application\\PromotionProcessor';

    public const string CARRIER_TAG = 'phil_commerce_logistics.carrier';

    public const string PRINT_ON_DEMAND_TAG = 'phil_commerce_logistics.print_on_demand';

    /** The core's tags, by their values: a module names them without reading the core's bundle class. */
    private const string DOCUMENT_TYPES_TAG = 'phil_commerce.document_types';

    private const string STOCK_COMMITMENTS_TAG = 'phil_commerce.stock_commitments';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_logistics';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('documents')
                    ->info("The delivery note and the picking slip in the core's register of document types, each numbered in its own series.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('delivery_note_prefix')->defaultValue('DN-')->end()
                        ->scalarNode('picking_slip_prefix')->defaultValue('PS-')->end()
                        ->integerNode('digits')->min(1)->max(18)->defaultValue(8)->end()
                    ->end()
                ->end()
                ->arrayNode('conventions')
                    ->info('The stock of convention items: an order placed online goes to print while a convention is near.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('window_days')->info('How many days ahead a convention counts as near.')->min(0)->max(ConventionStock::MAX_WINDOW_DAYS)->defaultValue(30)->end()
                        ->arrayNode('online_channels')
                            ->info('The channels whose orders are placed online, beside the orders without a channel.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('costing')
                    ->info('The value of the stock at cost, kept with each movement; units left on consignment are never part of it.')
                    ->canBeEnabled()
                    ->children()
                        ->enumNode('method')
                            ->info("How the shop's units leave: at the average of their pool, or the oldest layer first.")
                            ->values(array_map(static fn (CostingMethod $method): string => $method->value, CostingMethod::cases()))
                            ->defaultValue(CostingMethod::WeightedAverage->value)
                        ->end()
                        ->scalarNode('currency')->info('The ISO 4217 code of the currency the stock is valued in: every unit cost and landed cost is given in it.')->defaultNull()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $costing): bool => true === $costing['enabled'] && (!\is_string($costing['currency']) || 1 !== preg_match('/^[A-Z]{3}$/D', $costing['currency'])))
                        ->thenInvalid('Costing needs the ISO 4217 code of its currency, three capital letters.')
                    ->end()
                ->end()
                ->arrayNode('carriers')
                    ->info('The carriers whose rates and labels the shop asks for; their credentials come from the environment or the secrets vault, never from the configuration file itself.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('canada_post')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('api_key')->info('The key of the developer application: "%env(CANADA_POST_API_KEY)%".')->defaultNull()->end()
                                ->scalarNode('api_secret')->info('Its secret: "%env(CANADA_POST_API_SECRET)%".')->defaultNull()->end()
                                ->scalarNode('customer_number')->info('The customer number billed for the labels.')->defaultNull()->end()
                                ->scalarNode('contract_id')->info('The contract that gives commercial rates; none for counter rates.')->defaultNull()->end()
                                ->scalarNode('base_uri')->info('The address of the API: its test or its production gateway.')->defaultNull()->end()
                                ->scalarNode('token_uri')->info('The address that grants its OAuth 2.0 access tokens.')->defaultNull()->end()
                                ->enumNode('language')->values(['en-CA', 'fr-CA'])->defaultValue('en-CA')->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $carrier): bool => true === $carrier['enabled'] && \in_array(null, [$carrier['api_key'], $carrier['api_secret'], $carrier['customer_number'], $carrier['base_uri'], $carrier['token_uri']], true))
                                ->thenInvalid('Canada Post needs api_key, api_secret, customer_number, base_uri and token_uri.')
                            ->end()
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
            throw new LogicException('PhilCommerceLogisticsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceLogisticsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceLogisticsBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $builder->registerForAutoconfiguration(CarrierGateway::class)->addTag(self::CARRIER_TAG);
        $builder->registerForAutoconfiguration(PrintOnDemand::class)->addTag(self::PRINT_ON_DEMAND_TAG);
        $this->loadCarriers($config, $container);

        /** @var array{delivery_note_prefix: string, picking_slip_prefix: string, digits: int} $documents */
        $documents = $config['documents'];
        $services->set(LogisticsDocumentTypes::class)
            ->args([$documents['delivery_note_prefix'], $documents['picking_slip_prefix'], $documents['digits']])
            ->tag(self::DOCUMENT_TYPES_TAG);

        // Every service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(DeliveryOptions::class)->autowire()->public();
        // An order processor after the promotions, whose discounts reach the free-delivery threshold, and before the
        // core's taxes. The catalogue's processor is named by its class, without depending on the catalogue: the
        // constraint is ignored when the catalogue is not installed.
        $services->set(ShippingProcessor::class)->autowire()->tag('phil_commerce.order_processor', ['after' => self::PROMOTION_PROCESSOR, 'before' => TaxOrderProcessor::class]);
        /** @var array{window_days: int, online_channels: list<string>} $conventions */
        $conventions = $config['conventions'];
        $services->set(ConventionStock::class)->autowire()
            ->arg('$windowDays', $conventions['window_days'])
            ->arg('$onlineChannels', $conventions['online_channels'])
            ->arg('$printers', tagged_iterator(self::PRINT_ON_DEMAND_TAG))
            ->public();
        // The value of the stock, read by reports and the catalogue; empty while costing is disabled.
        $services->set(InventoryValue::class)->autowire();
        $services->alias(InventoryValuation::class, InventoryValue::class)->public();
        $this->loadCosting($config, $container);
        // Shipped units are announced on the application's event dispatcher, when it has one.
        $services->set(Stock::class)->autowire()
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid())
            ->arg('$costing', service(Costing::class)->nullOnInvalid())
            ->public();
        $services->set(Shipments::class)->autowire()->public();
        $services->set(Purchasing::class)->autowire()->arg('$costing', service(Costing::class)->nullOnInvalid())->public();
        $services->set(SerialNumbers::class)->autowire()->public();
        $services->set(ShippingWaves::class)->autowire()->public();
        $services->set(ShippingDocuments::class)->autowire()->public();
        // The stock of a sale paid later, held and freed by the core's deferred sales.
        $services->set(DeferredStock::class)->autowire()->tag(self::STOCK_COMMITMENTS_TAG);
        $services->set(ShippingLabels::class)->autowire()->arg('$carriers', tagged_iterator(self::CARRIER_TAG))->public();

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineLogisticsRepository::class] as $adapter) {
            $services->set($adapter)->args([service('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }
    }

    /**
     * The costing, when it is enabled: its method, its currency, and whose units leave a level that consigned units
     * share — the shop's first, unless the application points the alias of `StockOwnership` at its own service.
     *
     * @param array<array-key, mixed> $config
     */
    private function loadCosting(array $config, ContainerConfigurator $container): void
    {
        /** @var array{enabled: bool, method: string, currency: ?string} $costing */
        $costing = $config['costing'];

        if (!$costing['enabled']) {
            return;
        }

        $services = $container->services();
        $services->set(ShopUnitsFirst::class);
        $services->alias(StockOwnership::class, ShopUnitsFirst::class);
        $services->set(Costing::class)->args([
            service(DoctrineLogisticsRepository::class),
            CostingMethod::from($costing['method']),
            inline_service(Currency::class)->factory([Currency::class, 'of'])->args([$costing['currency']]),
            service(StockOwnership::class),
        ]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function loadCarriers(array $config, ContainerConfigurator $container): void
    {
        $canadaPost = \is_array($config['carriers'] ?? null) && \is_array($config['carriers']['canada_post'] ?? null) ? $config['carriers']['canada_post'] : [];

        if (true !== ($canadaPost['enabled'] ?? false)) {
            return;
        }

        $container->services()->set(CanadaPostCarrier::class)
            ->args([
                service('http_client'),
                inline_service(CanadaPostCredentials::class)->args([
                    $canadaPost['api_key'],
                    $canadaPost['api_secret'],
                    $canadaPost['customer_number'],
                    $canadaPost['base_uri'],
                    $canadaPost['token_uri'],
                    $canadaPost['contract_id'],
                    $canadaPost['language'],
                ]),
            ])
            ->tag(self::CARRIER_TAG)
            ->tag('kernel.reset', ['method' => 'reset']);
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
