<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\Application\Channels;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\Contract\OrderShipments;
use Phil\CommerceBundle\Application\Contract\StockCommitments;
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\OptionDraws;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\OrderAmendments;
use Phil\CommerceBundle\Application\OrderModifier;
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
use Phil\CommerceBundle\Application\SellableOffers;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Application\UnknownShipments;
use Phil\CommerceBundle\Domain\Contract\DeferredSaleRepository;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;
use Phil\CommerceBundle\Domain\Repository\QuoteRepository;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineChannelRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineQuoteRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineReceiptRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\AdjustmentsType;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\OrderLinesType;
use Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController;
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\CommerceBundle\PhilCommerceBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceBundle::class)]
final class PhilCommerceBundleTest extends TestCase
{
    public function testItWiresTheOrderModifierOverItsFourTagsAndTheOptions(): void
    {
        $container = $this->load(['tax_rates' => ['digital' => ['QC' => ['GST' => '5']], 'goods' => ['ON' => ['HST' => '13']]]]);

        self::assertSame('phil_commerce', new PhilCommerceBundle()->getContainerExtension()?->getAlias());

        $modifier = $container->getDefinition(OrderModifier::class);
        self::assertTrue($modifier->isPublic());
        self::assertSame(
            [PhilCommerceBundle::SELLABLE_RESOLVER_TAG, PhilCommerceBundle::UNIT_PRICE_RESOLVER_TAG, PhilCommerceBundle::ORDER_PROCESSOR_TAG, PhilCommerceBundle::SELLABLE_AVAILABILITY_TAG],
            array_map(static function (mixed $argument): string {
                self::assertInstanceOf(TaggedIteratorArgument::class, $argument);

                return $argument->getTag();
            }, \array_slice($modifier->getArguments(), 0, 4)),
        );
        $modifierOptions = $modifier->getArgument(4);
        self::assertInstanceOf(Reference::class, $modifierOptions);
        self::assertSame(LineOptions::class, (string) $modifierOptions);

        $visibilities = $container->getDefinition(SellableOffers::class);
        self::assertTrue($visibilities->isPublic());
        $selections = $visibilities->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $selections);
        self::assertSame(PhilCommerceBundle::SELLABLE_AVAILABILITY_TAG, $selections->getTag());

        $options = $container->getDefinition(LineOptions::class)->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $options);
        self::assertSame(PhilCommerceBundle::SELLABLE_OPTIONS_TAG, $options->getTag());

        $supplements = $container->getDefinition(OptionSupplementProcessor::class);
        self::assertSame([['before' => TaxOrderProcessor::class]], $supplements->getTag(PhilCommerceBundle::ORDER_PROCESSOR_TAG));
        $supplementOptions = $supplements->getArgument(0);
        self::assertInstanceOf(Reference::class, $supplementOptions);
        self::assertSame(LineOptions::class, (string) $supplementOptions);

        $draws = $container->getDefinition(OptionDraws::class);
        self::assertTrue($draws->isPublic());
        $drawers = $draws->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $drawers);
        self::assertSame(PhilCommerceBundle::OPTION_DRAWER_TAG, $drawers->getTag());

        self::assertSame(['digital' => ['QC' => ['GST' => '5']], 'goods' => ['ON' => ['HST' => '13']]], $container->getDefinition(ConfiguredTaxRates::class)->getArgument(0));
        self::assertSame(ConfiguredTaxRates::class, (string) $container->getAlias(TaxRateResolver::class));
        self::assertSame(UnknownShipments::class, (string) $container->getAlias(OrderShipments::class));
        self::assertTrue($container->getAlias(OrderShipments::class)->isPublic());
        self::assertTrue($container->hasDefinition(UnknownShipments::class));

        $processor = $container->getDefinition(TaxOrderProcessor::class);
        self::assertSame([[]], $processor->getTag(PhilCommerceBundle::ORDER_PROCESSOR_TAG));
        $argument = $processor->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(TaxRateResolver::class, (string) $argument);
    }

    public function testEachPortIsTaggedByAutoconfiguration(): void
    {
        $autoconfigured = $this->load([])->getAutoconfiguredInstanceof();

        self::assertSame([PhilCommerceBundle::SELLABLE_RESOLVER_TAG => [[]]], $autoconfigured[SellableResolver::class]->getTags());
        self::assertSame([PhilCommerceBundle::UNIT_PRICE_RESOLVER_TAG => [[]]], $autoconfigured[UnitPriceResolver::class]->getTags());
        self::assertSame([PhilCommerceBundle::ORDER_PROCESSOR_TAG => [[]]], $autoconfigured[OrderProcessor::class]->getTags());
        self::assertSame([PhilCommerceBundle::PAYMENT_GATEWAY_TAG => [[]]], $autoconfigured[PaymentGateway::class]->getTags());
        self::assertSame([PhilCommerceBundle::SELLABLE_AVAILABILITY_TAG => [[]]], $autoconfigured[SellableAvailability::class]->getTags());
        self::assertSame([PhilCommerceBundle::SELLABLE_OPTIONS_TAG => [[]]], $autoconfigured[SellableOptions::class]->getTags());
        self::assertSame([PhilCommerceBundle::OPTION_DRAWER_TAG => [[]]], $autoconfigured[OptionDrawer::class]->getTags());
        self::assertSame([PhilCommerceBundle::STOCK_COMMITMENTS_TAG => [[]]], $autoconfigured[StockCommitments::class]->getTags());
    }

    public function testDeferredSalesGoThroughTheTaggedCommitmentsAndTheirDoctrineStore(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        $sales = $container->getDefinition(DeferredSales::class);
        self::assertTrue($sales->isPublic());
        self::assertTrue($sales->isAutowired());
        $commitments = $sales->getArgument('$commitments');
        self::assertInstanceOf(TaggedIteratorArgument::class, $commitments);
        self::assertSame(PhilCommerceBundle::STOCK_COMMITMENTS_TAG, $commitments->getTag());
        $dispatcher = $sales->getArgument('$dispatcher');
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame('event_dispatcher', (string) $dispatcher);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $dispatcher->getInvalidBehavior());

        $entityManager = $container->getDefinition(DoctrineDeferredSaleRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $entityManager);
        self::assertSame('doctrine.orm.entity_manager', (string) $entityManager);
        self::assertSame(DoctrineDeferredSaleRepository::class, (string) $container->getAlias(DeferredSaleRepository::class));
        self::assertTrue($container->getAlias(DeferredSaleRepository::class)->isPublic());

        $alone = $this->load([]);
        self::assertFalse($alone->hasDefinition(DeferredSales::class));
        self::assertFalse($alone->hasAlias(DeferredSaleRepository::class));
    }

    public function testPaymentsGoThroughTheTaggedGateways(): void
    {
        $payments = $this->load([], ['DoctrineBundle' => DoctrineBundle::class])->getDefinition(Payments::class);

        self::assertTrue($payments->isPublic());
        self::assertTrue($payments->isAutowired());
        $gateways = $payments->getArgument('$gateways');
        self::assertInstanceOf(TaggedIteratorArgument::class, $gateways);
        self::assertSame(PhilCommerceBundle::PAYMENT_GATEWAY_TAG, $gateways->getTag());
    }

    public function testNoRateMeansAnEmptyTable(): void
    {
        self::assertSame([], $this->load([])->getDefinition(ConfiguredTaxRates::class)->getArgument(0));
    }

    public function testARateATaxRateCannotHoldIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"13%" is not a percentage a tax rate holds');

        $this->load(['tax_rates' => ['goods' => ['ON' => ['HST' => '13%']]]]);
    }

    /**
     * Categories, zones and rate codes are the application's own names: a dash stays a dash.
     */
    public function testDashedTaxNamesAreKeptAsWritten(): void
    {
        $container = $this->load([
            'tax_rates' => ['digital-goods' => ['CA-QC' => ['GST' => '5', 'QC-TVQ' => '9.975']]],
            'receipts' => ['issuer' => ['name' => 'Shop', 'tax_numbers' => ['QC-TVQ' => '1234567890 TQ0001']]],
        ], ['DoctrineBundle' => DoctrineBundle::class]);

        self::assertSame(['digital-goods' => ['CA-QC' => ['GST' => '5', 'QC-TVQ' => '9.975']]], $container->getDefinition(ConfiguredTaxRates::class)->getArgument(0));
        $issuer = $container->getDefinition(Receipts::class)->getArgument('$issuer');
        self::assertIsArray($issuer);
        self::assertSame(['QC-TVQ' => '1234567890 TQ0001'], $issuer['tax_numbers']);
    }

    public function testAPrefixAndItsDigitsFillTheNumberColumnAtMost(): void
    {
        $bundles = ['DoctrineBundle' => DoctrineBundle::class];
        $prefix = str_repeat('F', PhilCommerceBundle::DOCUMENT_NUMBER_LENGTH - 18);

        self::assertSame(['receipt' => $prefix, 'credit_note' => 'CN-'], $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'receipt_prefix' => $prefix, 'number_digits' => 18]], $bundles)->getDefinition(DoctrineDocumentNumbering::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, int|string>, string}>
     */
    public static function overlongNumbers(): iterable
    {
        yield 'a long receipt prefix' => [['receipt_prefix' => str_repeat('F', 15), 'number_digits' => 18], 'The receipt_prefix "FFFFFFFFFFFFFFF" and 18 digits make numbers longer than the 32 characters a document number holds.'];
        yield 'a long credit note prefix' => [['credit_note_prefix' => 'CREDIT-NOTE-2026-', 'number_digits' => 16], 'The credit_note_prefix "CREDIT-NOTE-2026-" and 16 digits make numbers longer than the 32 characters a document number holds.'];
    }

    /**
     * @param array<string, int|string> $receipts
     */
    #[DataProvider('overlongNumbers')]
    public function testANumberLongerThanItsColumnIsRefused(array $receipts, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], ...$receipts]]);
    }

    public function testTwoSeriesWithOnePrefixAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The receipt_prefix and the credit_note_prefix are both "F-": the two series would give the same numbers.');

        $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'receipt_prefix' => 'F-', 'credit_note_prefix' => 'F-']]);
    }

    public function testReceiptsAreOffUntilEnabled(): void
    {
        self::assertFalse($this->load([], ['DoctrineBundle' => DoctrineBundle::class])->hasDefinition(Receipts::class));
        self::assertFalse($this->load(['receipts' => ['issuer' => ['name' => 'Shop']]])->hasDefinition(Receipts::class));
        self::assertFalse($this->load(['receipts' => ['enabled' => false, 'issuer' => ['name' => 'Shop']]], ['DoctrineBundle' => DoctrineBundle::class])->hasDefinition(Receipts::class));
    }

    public function testEnabledReceiptsAreIssuedByTheConfiguredIssuer(): void
    {
        $issuer = ['name' => 'Le Magasin', 'address' => ['1 rue Principale'], 'tax_numbers' => ['GST' => '123456789 RT0001']];
        $container = $this->load(['receipts' => ['issuer' => $issuer, 'receipt_prefix' => 'F', 'number_digits' => 6]], ['DoctrineBundle' => DoctrineBundle::class]);

        $receipts = $container->getDefinition(Receipts::class);
        self::assertTrue($receipts->isPublic());
        self::assertTrue($receipts->isAutowired());
        self::assertSame($issuer, $receipts->getArgument('$issuer'));

        $numbering = $container->getDefinition(DoctrineDocumentNumbering::class);
        self::assertSame(['receipt' => 'F', 'credit_note' => 'CN-'], $numbering->getArgument(1));
        self::assertSame(6, $numbering->getArgument(2));
        self::assertSame(DoctrineDocumentNumbering::class, (string) $container->getAlias(DocumentNumbering::class));
        self::assertSame(DoctrineReceiptRepository::class, (string) $container->getAlias(ReceiptRepository::class));
        $entityManager = $numbering->getArgument(0);
        self::assertInstanceOf(Reference::class, $entityManager);
        self::assertSame('doctrine.orm.entity_manager', (string) $entityManager);
        $repositoryManager = $container->getDefinition(DoctrineReceiptRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $repositoryManager);
        self::assertSame('doctrine.orm.entity_manager', (string) $repositoryManager);
    }

    public function testDefaultNumbersReadRAndCnOnEightDigits(): void
    {
        $numbering = $this->load([], ['DoctrineBundle' => DoctrineBundle::class])->getDefinition(DoctrineDocumentNumbering::class);

        self::assertSame(['receipt' => 'R-', 'credit_note' => 'CN-'], $numbering->getArgument(1));
        self::assertSame(8, $numbering->getArgument(2));
    }

    public function testReceiptsTurnedOffAreOffEvenWithAnIssuer(): void
    {
        self::assertFalse($this->load(['receipts' => ['enabled' => false, 'issuer' => ['name' => 'Shop']]])->hasDefinition(Receipts::class));
    }

    public function testOneToEighteenDigitsAreAccepted(): void
    {
        $bundles = ['DoctrineBundle' => DoctrineBundle::class];

        self::assertSame(1, $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'number_digits' => 1]], $bundles)->getDefinition(DoctrineDocumentNumbering::class)->getArgument(2));
        self::assertSame(18, $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'number_digits' => 18]], $bundles)->getDefinition(DoctrineDocumentNumbering::class)->getArgument(2));
    }

    public function testReceiptsWithoutAnIssuerAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Receipts need their issuer: set phil_commerce.receipts.issuer.name.');

        $this->load(['receipts' => ['enabled' => true]]);
    }

    public function testNumbersOfNineteenDigitsAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'number_digits' => 19]]);
    }

    public function testNumbersWithoutDigitsAreRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'number_digits' => 0]]);
    }

    public function testWithoutDoctrineNothingIsMappedOrStored(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(DoctrineOrderRepository::class));
        self::assertFalse($container->hasAlias(OrderRepository::class));
        self::assertFalse($container->hasAlias(PaymentRepository::class));
        self::assertFalse($container->hasDefinition(Payments::class));
        self::assertFalse($container->hasDefinition(Channels::class));
        self::assertFalse($container->hasAlias(ChannelRepository::class));
        self::assertFalse($container->hasDefinition(PaymentWebhookController::class));
        self::assertFalse($container->hasAlias(DocumentNumbering::class));
        self::assertFalse($container->hasAlias(ReceiptRepository::class));

        $prepended = new ContainerBuilder();
        $extension = new PhilCommerceBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($prepended);

        self::assertSame([], $prepended->getExtensionConfig('doctrine'));
    }

    public function testQuotesAmendmentsAndThePdfAreWired(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        foreach ([Quotes::class, OrderAmendments::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        self::assertSame(DoctrineQuoteRepository::class, (string) $container->getAlias(QuoteRepository::class));
        self::assertTrue($container->getAlias(QuoteRepository::class)->isPublic());
        $argument = $container->getDefinition(DoctrineQuoteRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        self::assertTrue($container->getDefinition(ReceiptPdf::class)->isPublic());

        $alone = $this->load([]);
        self::assertTrue($alone->getDefinition(ReceiptPdf::class)->isPublic());
        self::assertFalse($alone->hasDefinition(Quotes::class));
        self::assertFalse($alone->hasDefinition(OrderAmendments::class));
        self::assertFalse($alone->hasAlias(QuoteRepository::class));
    }

    public function testWithDoctrineOrdersAreMappedAndStored(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        $repository = $container->getDefinition(DoctrineOrderRepository::class);
        $argument = $repository->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        self::assertSame(DoctrineOrderRepository::class, (string) $container->getAlias(OrderRepository::class));
        self::assertTrue($container->getAlias(OrderRepository::class)->isPublic());
        self::assertSame(DoctrinePaymentRepository::class, (string) $container->getAlias(PaymentRepository::class));
        self::assertSame(DoctrineChannelRepository::class, (string) $container->getAlias(ChannelRepository::class));
        self::assertTrue($container->getAlias(ChannelRepository::class)->isPublic());
        $channelManager = $container->getDefinition(DoctrineChannelRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $channelManager);
        self::assertSame('doctrine.orm.entity_manager', (string) $channelManager);
        self::assertTrue($container->getDefinition(Channels::class)->isPublic());
        self::assertTrue($container->getDefinition(Channels::class)->isAutowired());
        self::assertTrue($container->getAlias(PaymentRepository::class)->isPublic());
        $paymentRepository = $container->getDefinition(DoctrinePaymentRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $paymentRepository);
        self::assertSame('doctrine.orm.entity_manager', (string) $paymentRepository);

        $controller = $container->getDefinition(PaymentWebhookController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertSame([Payments::class, 'doctrine.orm.entity_manager'], array_map(static fn (mixed $argument): string => $argument instanceof Reference ? (string) $argument : '', $controller->getArguments()));

        $prepended = new ContainerBuilder();
        $prepended->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $prepended->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = new PhilCommerceBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($prepended);

        self::assertSame([[
            'dbal' => ['types' => [OrderLinesType::NAME => OrderLinesType::class, AdjustmentsType::NAME => AdjustmentsType::class]],
            'orm' => ['mappings' => ['PhilCommerceBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => \dirname((string) new ReflectionClass(PhilCommerceBundle::class)->getFileName()).'/Domain/Model',
                'prefix' => 'Phil\\CommerceBundle\\Domain\\Model',
            ], 'PhilCommerceBundleSequences' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => \dirname((string) new ReflectionClass(PhilCommerceBundle::class)->getFileName()).'/Infrastructure/Doctrine/Entity',
                'prefix' => 'Phil\\CommerceBundle\\Infrastructure\\Doctrine\\Entity',
            ]]],
        ]], $prepended->getExtensionConfig('doctrine'));
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $bundles
     */
    private function load(array $config, array $bundles = []): ContainerBuilder
    {
        $extension = new PhilCommerceBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        $extension->load([$config], $container);

        return $container;
    }
}
