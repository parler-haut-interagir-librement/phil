<?php

declare(strict_types=1);

namespace Phil\CommerceBundle;

use InvalidArgumentException;
use Override;
use Phil\CommerceBundle\Application\Channels;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Application\Contract\OrderShipments;
use Phil\CommerceBundle\Application\Contract\StockCommitments;
use Phil\CommerceBundle\Application\Contract\TransitionForcing;
use Phil\CommerceBundle\Application\CoreDocumentTypes;
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Application\DenyForcing;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Application\GiftWrapping;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\ManualDiscountProcessor;
use Phil\CommerceBundle\Application\OptionDraws;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\OrderAmendments;
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Application\OrderExpiry;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Application\PaymentLinks;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Application\Port\OptionDrawer;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Application\Quotes;
use Phil\CommerceBundle\Application\Receipts;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Application\SellableOffers;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Application\UnknownShipments;
use Phil\CommerceBundle\Application\WishLists;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineChannelRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderChangeRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderJournal;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLinkRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineQuoteRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineReceiptRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineRefundRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineWishListRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\AdjustmentsType;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\OrderLinesType;
use Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController;
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\CommerceBundle\Infrastructure\Workflow\OrderWorkflowPass;
use Phil\Money\Currency;
use Phil\Money\Money;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\StateMachine;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The core of commerce, on which the module bundles plug — subscriptions, catalogue, logistics, wholesale — each
 * a package of its own that an application installs or not. Autoconfiguration fills eight tags with the services
 * implementing the ports; the order modifier reads five of them — what is sold, its options, at what unit price, the
 * processors that recompute an order's adjustments, and what a channel sells —, the payments read the gateways, and
 * the draws of a paid order read the option drawers, and the deferred sales read the stock commitments. Taxes are the core's own processor, on the rates of
 * `phil_commerce.tax_rates` unless the application aliases its own `TaxRateResolver`. Payments go through the
 * gateways tagged `phil_commerce.payment_gateway`. With DoctrineBundle, orders and payments are mapped and stored,
 * and the webhook controller receives the processors' notifications.
 *
 * The order's life cycle is the `phil_commerce_order` workflow of `symfony/workflow`, added to the container's
 * registry when there is one. The gift wrapping and the manual discounts are order processors of the core; wish
 * lists, payment links, changes of placed orders, expiry, refunds in two steps and the document register come with
 * DoctrineBundle.
 */
final class PhilCommerceBundle extends AbstractBundle
{
    public const string SELLABLE_RESOLVER_TAG = 'phil_commerce.sellable_resolver';

    public const string UNIT_PRICE_RESOLVER_TAG = 'phil_commerce.unit_price_resolver';

    public const string ORDER_PROCESSOR_TAG = 'phil_commerce.order_processor';

    public const string PAYMENT_GATEWAY_TAG = 'phil_commerce.payment_gateway';

    public const string SELLABLE_AVAILABILITY_TAG = 'phil_commerce.sellable_availability';

    public const string SELLABLE_OPTIONS_TAG = 'phil_commerce.sellable_options';

    public const string OPTION_DRAWER_TAG = 'phil_commerce.option_drawer';

    public const string STOCK_COMMITMENTS_TAG = 'phil_commerce.stock_commitments';

    public const string DOCUMENT_TYPES_TAG = 'phil_commerce.document_types';

    /** The catalogue module's promotions, which the manual discounts run after when installed. */
    private const string PROMOTION_PROCESSOR = 'Phil\\CommercePromotionsBundle\\Application\\PromotionProcessor';

    /** The length of the number column of `phil_commerce_receipt`, which a prefix and its digits must fit. */
    public const int DOCUMENT_NUMBER_LENGTH = 32;

    #[Override]
    protected string $extensionAlias = 'phil_commerce';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('tax_rates')
                    ->info('For each tax category, for each zone, the rates that apply as their percentages: {digital: {QC: {GST: "5", QST: "9.975"}}}.')
                    ->useAttributeAsKey('category')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->useAttributeAsKey('zone')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->useAttributeAsKey('code')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always($this->checkedTaxRates(...))
                    ->end()
                ->end()
                ->arrayNode('receipts')
                    ->info('Receipts and credit notes: who issues them, and how their numbers read.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('issuer')
                            ->children()
                                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                                ->arrayNode('address')
                                    ->info('The lines of the address, in the order they are printed.')
                                    ->scalarPrototype()->cannotBeEmpty()->end()
                                ->end()
                                ->arrayNode('tax_numbers')
                                    ->info('The registration number of each tax collected, by rate code: {GST: "123456789 RT0001"}.')
                                    ->useAttributeAsKey('code')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->cannotBeEmpty()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('receipt_prefix')
                            ->info("Printed before the number of each receipt; differs from the credit notes' prefix.")
                            ->defaultValue('R-')
                        ->end()
                        ->scalarNode('credit_note_prefix')
                            ->info("Printed before the number of each credit note; differs from the receipts' prefix.")
                            ->defaultValue('CN-')
                        ->end()
                        ->integerNode('number_digits')
                            ->info('The sequence is padded with zeros to this many digits.')
                            ->defaultValue(8)
                            ->min(1)
                            ->max(18)
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $receipts): bool => $receipts['enabled'] && !isset($receipts['issuer']))
                        ->thenInvalid('Receipts need their issuer: set phil_commerce.receipts.issuer.name.')
                    ->end()
                    ->validate()
                        ->always($this->checkedNumbering(...))
                    ->end()
                ->end()
                ->arrayNode('document_types')
                    ->info('The application\'s own document types, each numbered in a series of its own: {packing_slip: {prefix: "PS-"}}.')
                    ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('prefix')->defaultValue('')->end()
                            ->integerNode('digits')->defaultValue(8)->min(1)->max(18)->end()
                            ->enumNode('visibility')
                                ->info("How far the buyer sees its documents: listed among the buyer's documents, by direct link only, or kept for the store.")
                                ->values(array_map(static fn (Visibility $visibility): string => $visibility->value, Visibility::cases()))
                                ->defaultValue(Visibility::Hidden->value)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('order_statuses')
                    ->info("The application's statuses, each attached to the state it belongs to: {awaiting_pickup: placed}.")
                    ->useAttributeAsKey('status')
                    ->normalizeKeys(false)
                    ->enumPrototype()->values(array_map(static fn (OrderState $state): string => $state->value, OrderState::cases()))->end()
                ->end()
                ->arrayNode('gift_wrapping')
                    ->info('Whether the store wraps gifts, offers a recycled wrap, and what each costs per currency.')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('recycled_option')->defaultTrue()->end()
                        ->scalarNode('label')->defaultValue('Gift wrapping')->cannotBeEmpty()->end()
                        ->arrayNode('prices')
                            ->info('The price of a wrapping per currency, as a decimal: {CAD: "4.00"}; free when absent.')
                            ->useAttributeAsKey('currency')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('recycled_prices')
                            ->info('The price of a recycled wrapping per currency; the plain price when absent.')
                            ->useAttributeAsKey('currency')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('offline_payments')
                    ->info('The methods paid offline, confirmed by someone of the store: [cheque, bank_transfer].')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $method): bool => !\is_string($method) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method))
                            ->thenInvalid('An offline payment method is up to 32 lowercase letters, digits or "_", starting with a letter: %s.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('payment_links')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('default_hours')->defaultValue(72)->min(1)->end()
                        ->integerNode('max_hours')->defaultValue(720)->min(1)->max(8_760)->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $links): bool => $links['default_hours'] > $links['max_hours'])
                        ->thenInvalid('A payment link lasts by default no longer than its most: default_hours exceeds max_hours.')
                    ->end()
                ->end()
                ->arrayNode('order_expiry')
                    ->info('For orders of no channel the store knows: hours before an unpaid order expires, days before an expired one is deleted; null for never.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('unpaid_hours')->defaultValue(24)->min(1)->max(8_760)->end()
                        ->integerNode('delete_after_days')->defaultValue(30)->min(1)->max(3_650)->end()
                    ->end()
                ->end()
                ->arrayNode('refunds')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('single_step')
                            ->info('Whether granting a refund executes it at once, for a store where one person holds both rights.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new OrderWorkflowPass());
    }

    /**
     * The column types of an order and its mapping, handed to DoctrineBundle before it loads.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', [
            'dbal' => ['types' => [
                OrderLinesType::NAME => OrderLinesType::class,
                AdjustmentsType::NAME => AdjustmentsType::class,
            ]],
            'orm' => ['mappings' => [
                'PhilCommerceBundle' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => __DIR__.'/Domain/Model',
                    'prefix' => 'Phil\CommerceBundle\Domain\Model',
                ],
                'PhilCommerceBundleSequences' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => __DIR__.'/Infrastructure/Doctrine/Entity',
                    'prefix' => 'Phil\CommerceBundle\Infrastructure\Doctrine\Entity',
                ],
            ]],
        ]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{tax_rates: array<string, array<string, array<string, string>>>, receipts: array{enabled: bool, issuer?: array{name: string, address: list<string>, tax_numbers: array<string, string>}, receipt_prefix: string, credit_note_prefix: string, number_digits: int}, document_types: array<string, array{prefix: string, digits: int, visibility: string}>, order_statuses: array<string, string>, gift_wrapping: array{enabled: bool, recycled_option: bool, label: string, prices: array<string, string>, recycled_prices: array<string, string>}, offline_payments: list<string>, payment_links: array{default_hours: int, max_hours: int}, order_expiry: array{unpaid_hours: int|null, delete_after_days: int|null}, refunds: array{single_step: bool}} $config */
        $builder->registerForAutoconfiguration(SellableResolver::class)->addTag(self::SELLABLE_RESOLVER_TAG);
        $builder->registerForAutoconfiguration(UnitPriceResolver::class)->addTag(self::UNIT_PRICE_RESOLVER_TAG);
        $builder->registerForAutoconfiguration(OrderProcessor::class)->addTag(self::ORDER_PROCESSOR_TAG);
        $builder->registerForAutoconfiguration(PaymentGateway::class)->addTag(self::PAYMENT_GATEWAY_TAG);
        $builder->registerForAutoconfiguration(SellableAvailability::class)->addTag(self::SELLABLE_AVAILABILITY_TAG);
        $builder->registerForAutoconfiguration(SellableOptions::class)->addTag(self::SELLABLE_OPTIONS_TAG);
        $builder->registerForAutoconfiguration(OptionDrawer::class)->addTag(self::OPTION_DRAWER_TAG);
        $builder->registerForAutoconfiguration(StockCommitments::class)->addTag(self::STOCK_COMMITMENTS_TAG);
        $builder->registerForAutoconfiguration(DocumentTypes::class)->addTag(self::DOCUMENT_TYPES_TAG);

        // Read once, at compile time: a type the register refuses, or a price a currency cannot hold, fails the build.
        new DocumentRegister([new CoreDocumentTypes($config['receipts']['receipt_prefix'], $config['receipts']['credit_note_prefix'], $config['receipts']['number_digits'], $config['document_types'])])->types();

        foreach (['prices', 'recycled_prices'] as $key) {
            foreach ($config['gift_wrapping'][$key] as $currency => $price) {
                Money::ofDecimal($price, Currency::of($currency));
            }
        }

        $services = $container->services();
        $services->set(ConfiguredTaxRates::class)->args([$config['tax_rates']]);
        $services->alias(TaxRateResolver::class, ConfiguredTaxRates::class);
        // No priority: the modules place their processors before or after it by its class.
        $services->set(TaxOrderProcessor::class)
            ->args([service(TaxRateResolver::class)])
            ->tag(self::ORDER_PROCESSOR_TAG);
        $services->set(LineOptions::class)->args([tagged_iterator(self::SELLABLE_OPTIONS_TAG)]);
        // Before the taxes, which tax what the options add.
        $services->set(OptionSupplementProcessor::class)
            ->args([service(LineOptions::class)])
            ->tag(self::ORDER_PROCESSOR_TAG, ['before' => TaxOrderProcessor::class]);
        // After the options and the catalogue's promotions, before the taxes, which tax each line once discounted.
        $services->set(ManualDiscountProcessor::class)
            ->tag(self::ORDER_PROCESSOR_TAG, ['after' => [OptionSupplementProcessor::class, self::PROMOTION_PROCESSOR], 'before' => TaxOrderProcessor::class]);
        $services->set(GiftWrapping::class)
            ->args([
                $config['gift_wrapping']['enabled'],
                $config['gift_wrapping']['recycled_option'],
                $config['gift_wrapping']['prices'],
                $config['gift_wrapping']['recycled_prices'],
                $config['gift_wrapping']['label'],
            ])
            ->tag(self::ORDER_PROCESSOR_TAG)
            ->public();
        $services->set(OptionDraws::class)->args([tagged_iterator(self::OPTION_DRAWER_TAG)])->public();
        $services->set(ReceiptPdf::class)->public();

        $services->set(OrderMarkingStore::class);
        $services->set(OrderWorkflow::SERVICE, StateMachine::class)
            ->factory(OrderWorkflow::create(...))
            ->args([service(OrderMarkingStore::class), service('event_dispatcher')->nullOnInvalid()])
            ->public();
        $services->set(UnknownShipments::class);
        $services->alias(OrderShipments::class, UnknownShipments::class)->public();
        $services->set(DenyForcing::class);
        $services->alias(TransitionForcing::class, DenyForcing::class);
        $services->set(OrderLifecycle::class)
            ->args([
                service(OrderWorkflow::SERVICE),
                service(TransitionForcing::class),
                service(OrderJournal::class)->nullOnInvalid(),
                $config['order_statuses'],
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->public();

        $services->set(SellableOffers::class)->args([tagged_iterator(self::SELLABLE_AVAILABILITY_TAG)])->public();
        $services->set(OrderModifier::class)
            ->args([
                tagged_iterator(self::SELLABLE_RESOLVER_TAG),
                tagged_iterator(self::UNIT_PRICE_RESOLVER_TAG),
                tagged_iterator(self::ORDER_PROCESSOR_TAG),
                tagged_iterator(self::SELLABLE_AVAILABILITY_TAG),
                service(LineOptions::class),
                service(GiftWrapping::class),
                service(OrderLifecycle::class),
            ])
            ->public();
        $services->set(CoreDocumentTypes::class)
            ->args([$config['receipts']['receipt_prefix'], $config['receipts']['credit_note_prefix'], $config['receipts']['number_digits'], $config['document_types']])
            ->tag(self::DOCUMENT_TYPES_TAG);
        $services->set(DocumentRegister::class)
            ->args([tagged_iterator(self::DOCUMENT_TYPES_TAG), service(DocumentSeries::class)->nullOnInvalid()])
            ->public();

        // Payments and receipts need their repositories: Doctrine's, below. An application storing them otherwise
        // declares these two services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(Channels::class)->autowire()->public();
        $services->set(Quotes::class)->autowire()->public();
        $services->set(OrderAmendments::class)->autowire()->public();
        $services->set(Payments::class)
            ->autowire()
            ->arg('$gateways', tagged_iterator(self::PAYMENT_GATEWAY_TAG))
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid())
            ->arg('$offlineMethods', array_values(array_unique($config['offline_payments'])))
            ->arg('$clock', service('clock')->nullOnInvalid())
            ->public();
        $services->set(WishLists::class)
            ->autowire()
            ->tag('kernel.event_listener', ['event' => OrderPurchased::class, 'method' => 'recordPurchase'])
            ->public();
        $services->set(PaymentLinks::class)
            ->autowire()
            ->arg('$defaultHours', $config['payment_links']['default_hours'])
            ->arg('$maxHours', $config['payment_links']['max_hours'])
            ->public();
        $services->set(OrderChanges::class)->autowire()->public();
        $services->set(OrderExpiry::class)
            ->autowire()
            ->arg('$unpaidHours', $config['order_expiry']['unpaid_hours'])
            ->arg('$retentionDays', $config['order_expiry']['delete_after_days'])
            ->public();
        $services->set(Refunds::class)
            ->autowire()
            ->arg('$singleStep', $config['refunds']['single_step'])
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid())
            ->public();
        $services->set(DeferredSales::class)
            ->autowire()
            ->arg('$commitments', tagged_iterator(self::STOCK_COMMITMENTS_TAG))
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid())
            ->public();

        if (isset($config['receipts']['issuer']) && $config['receipts']['enabled']) {
            $services->set(Receipts::class)
                ->autowire()
                ->arg('$issuer', $config['receipts']['issuer'])
                ->public();
        }

        $services->set(DoctrineDocumentNumbering::class)->args([
            service('doctrine.orm.entity_manager'),
            ['receipt' => $config['receipts']['receipt_prefix'], 'credit_note' => $config['receipts']['credit_note_prefix']],
            $config['receipts']['number_digits'],
        ]);
        $services->alias(DocumentNumbering::class, DoctrineDocumentNumbering::class);
        $services->alias(DocumentSeries::class, DoctrineDocumentNumbering::class);
        $services->set(DoctrineChannelRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineOrderRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrinePaymentRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineReceiptRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineQuoteRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineDeferredSaleRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineWishListRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrinePaymentLinkRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineOrderChangeRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineOrderJournal::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrineRefundRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(DoctrinePaymentLock::class)->args([service('doctrine.orm.entity_manager')]);
        $services->set(BinaryKeysListener::class)->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineChannelRepository::class, DoctrineOrderRepository::class, DoctrinePaymentRepository::class, DoctrineReceiptRepository::class, DoctrineQuoteRepository::class, DoctrineDeferredSaleRepository::class, DoctrineWishListRepository::class, DoctrinePaymentLinkRepository::class, DoctrineOrderChangeRepository::class, DoctrineOrderJournal::class, DoctrineRefundRepository::class, DoctrinePaymentLock::class] as $adapter) {
            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        if (class_exists(Request::class)) {
            $services->set(PaymentWebhookController::class)
                ->args([service(Payments::class), service('doctrine.orm.entity_manager'), service('logger')->nullOnInvalid()])
                ->tag('controller.service_arguments')
                ->public();
        }
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }

    /**
     * The two series' numbers fit the document's number column and never meet: a prefix and its padded sequence
     * take at most `DOCUMENT_NUMBER_LENGTH` characters, and the two prefixes differ.
     *
     * @param array{receipt_prefix: string, credit_note_prefix: string, number_digits: int, ...} $receipts
     *
     * @return array{receipt_prefix: string, credit_note_prefix: string, number_digits: int, ...}
     */
    private function checkedNumbering(array $receipts): array
    {
        foreach (['receipt_prefix', 'credit_note_prefix'] as $key) {
            if (self::DOCUMENT_NUMBER_LENGTH < \strlen($receipts[$key]) + $receipts['number_digits']) {
                throw new InvalidArgumentException(\sprintf('The %s "%s" and %d digits make numbers longer than the %d characters a document number holds.', $key, $receipts[$key], $receipts['number_digits'], self::DOCUMENT_NUMBER_LENGTH));
            }
        }

        if ($receipts['receipt_prefix'] === $receipts['credit_note_prefix']) {
            throw new InvalidArgumentException(\sprintf('The receipt_prefix and the credit_note_prefix are both "%s": the two series would give the same numbers.', $receipts['receipt_prefix']));
        }

        return $receipts;
    }

    /**
     * Every percentage read once, at compile time, so that a rate a `TaxRate` cannot hold fails the build.
     *
     * @param array<string, array<string, array<string, string>>> $rates
     *
     * @return array<string, array<string, array<string, string>>>
     */
    private function checkedTaxRates(array $rates): array
    {
        new ConfiguredTaxRates($rates);

        return $rates;
    }
}
