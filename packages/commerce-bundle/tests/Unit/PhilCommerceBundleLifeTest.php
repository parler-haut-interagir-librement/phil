<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\Application\Contract\DocumentSeries;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Application\Contract\TransitionForcing;
use Phil\CommerceBundle\Application\CoreDocumentTypes;
use Phil\CommerceBundle\Application\DenyForcing;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Application\GiftWrapping;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\ManualDiscountProcessor;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Application\OrderExpiry;
use Phil\CommerceBundle\Application\OrderLifecycle;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Application\PaymentLinks;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Application\WishLists;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderChangeRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderJournal;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLinkRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineRefundRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineWishListRepository;
use Phil\CommerceBundle\PhilCommerceBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

/**
 * What the order's life adds to the container: the workflow, the processors of the core, the services of the
 * application layer and the settings that tune them.
 */
#[CoversClass(PhilCommerceBundle::class)]
final class PhilCommerceBundleLifeTest extends TestCase
{
    /**
     * The processor of phil/commerce-promotions-bundle, a module of its own: named by its class, which need not be
     * installed.
     */
    private const string PROMOTION_PROCESSOR = 'Phil\\CommercePromotionsBundle\\Application\\PromotionProcessor';

    private const array DOCTRINE = ['DoctrineBundle' => DoctrineBundle::class];

    public function testTheOrdersWorkflowIsBuiltOverItsMarkingStoreAndTheOptionalDispatcher(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->hasDefinition(OrderMarkingStore::class));
        self::assertFalse($container->getDefinition(OrderMarkingStore::class)->isPublic());

        $workflow = $container->getDefinition(OrderWorkflow::SERVICE);
        self::assertTrue($workflow->isPublic());
        self::assertSame([OrderMarkingStore::class, 'event_dispatcher'], $this->references($workflow));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->reference($workflow, 1)->getInvalidBehavior());
        self::assertNotNull($workflow->getFactory());
    }

    public function testTheLifecycleDeniesForcingUnlessTheApplicationSaysWho(): void
    {
        $container = $this->load(['order_statuses' => ['awaiting-pickup' => 'placed']]);

        self::assertTrue($container->hasDefinition(DenyForcing::class));
        self::assertSame(DenyForcing::class, (string) $container->getAlias(TransitionForcing::class));

        $lifecycle = $container->getDefinition(OrderLifecycle::class);
        self::assertTrue($lifecycle->isPublic());
        self::assertSame(OrderWorkflow::SERVICE, (string) $this->reference($lifecycle, 0));
        self::assertSame(TransitionForcing::class, (string) $this->reference($lifecycle, 1));
        self::assertSame(OrderJournal::class, (string) $this->reference($lifecycle, 2));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->reference($lifecycle, 2)->getInvalidBehavior());
        self::assertSame(['awaiting-pickup' => 'placed'], $lifecycle->getArgument(3));
        self::assertSame('event_dispatcher', (string) $this->reference($lifecycle, 4));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->reference($lifecycle, 4)->getInvalidBehavior());

        $modifier = $container->getDefinition(OrderModifier::class);
        self::assertSame([LineOptions::class, GiftWrapping::class, OrderLifecycle::class], \array_slice($this->references($modifier), 4));
    }

    public function testManualDiscountsRunAfterTheOptionsAndThePromotionsAndBeforeTheTaxes(): void
    {
        $processor = $this->load([])->getDefinition(ManualDiscountProcessor::class);

        self::assertSame([[
            'after' => [OptionSupplementProcessor::class, self::PROMOTION_PROCESSOR],
            'before' => TaxOrderProcessor::class,
        ]], $processor->getTag(PhilCommerceBundle::ORDER_PROCESSOR_TAG));
    }

    public function testTheGiftWrappingIsAProcessorOfTheConfiguredPolicy(): void
    {
        $default = $this->load([])->getDefinition(GiftWrapping::class);

        self::assertTrue($default->isPublic());
        self::assertSame([[]], $default->getTag(PhilCommerceBundle::ORDER_PROCESSOR_TAG));
        self::assertSame([false, true, [], [], 'Gift wrapping'], $default->getArguments());

        $configured = $this->load(['gift_wrapping' => ['enabled' => true, 'recycled_option' => false, 'label' => 'Wrap', 'prices' => ['CAD' => '4.00'], 'recycled_prices' => ['CAD' => '3.50']]])->getDefinition(GiftWrapping::class);

        self::assertSame([true, false, ['CAD' => '4.00'], ['CAD' => '3.50'], 'Wrap'], $configured->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedWrappingPrices(): iterable
    {
        yield 'a price a currency cannot hold' => [['prices' => ['CAD' => '4.001']], '4.001'];
        yield 'a price that is no number' => [['prices' => ['CAD' => 'four']], 'four'];
        yield 'a recycled price a currency cannot hold' => [['recycled_prices' => ['CAD' => '3.505']], '3.505'];
        yield 'a currency of no ISO code' => [['prices' => ['XX-' => '4.00']], '"XX-" is not a known ISO 4217 currency code.'];
        yield 'a recycled currency of no ISO code' => [['recycled_prices' => ['XX-' => '4.00']], '"XX-" is not a known ISO 4217 currency code.'];
    }

    /**
     * @param array<string, mixed> $wrapping
     */
    #[DataProvider('refusedWrappingPrices')]
    public function testAWrappingPriceTheCurrencyCannotHoldFailsTheBuild(array $wrapping, string $message): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['gift_wrapping' => ['enabled' => true, ...$wrapping]]);
    }

    public function testTheDocumentTypesAreRegisteredFromTheReceiptsAndTheApplicationsOwn(): void
    {
        $container = $this->load(['receipts' => ['issuer' => ['name' => 'Shop'], 'receipt_prefix' => 'F-', 'credit_note_prefix' => 'C-', 'number_digits' => 6], 'document_types' => ['packing_slip' => ['prefix' => 'PS-', 'digits' => 4, 'visibility' => 'unlisted']]], self::DOCTRINE);

        self::assertSame([PhilCommerceBundle::DOCUMENT_TYPES_TAG => [[]]], $container->getAutoconfiguredInstanceof()[DocumentTypes::class]->getTags());

        $core = $container->getDefinition(CoreDocumentTypes::class);
        self::assertSame(['F-', 'C-', 6, ['packing_slip' => ['prefix' => 'PS-', 'digits' => 4, 'visibility' => 'unlisted']]], $core->getArguments());
        self::assertSame([[]], $core->getTag(PhilCommerceBundle::DOCUMENT_TYPES_TAG));

        $register = $container->getDefinition(DocumentRegister::class);
        self::assertTrue($register->isPublic());
        $types = $register->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $types);
        self::assertSame(PhilCommerceBundle::DOCUMENT_TYPES_TAG, $types->getTag());
        self::assertSame(DocumentSeries::class, (string) $this->reference($register, 1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->reference($register, 1)->getInvalidBehavior());
        self::assertSame(DoctrineDocumentNumbering::class, (string) $container->getAlias(DocumentSeries::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedDocumentTypes(): iterable
    {
        yield 'a type sharing the receipt prefix' => [['document_types' => ['packing_slip' => ['prefix' => 'R-']]]];
        yield 'a type of the receipts code' => [['document_types' => ['receipt' => ['prefix' => 'X-']]]];
        yield 'a code with a dash' => [['document_types' => ['packing-slip' => ['prefix' => 'PS-']]]];
        yield 'an unknown visibility' => [['document_types' => ['packing_slip' => ['prefix' => 'PS-', 'visibility' => 'public']]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedDocumentTypes')]
    public function testADocumentTypeTheRegisterRefusesFailsTheBuild(array $config): void
    {
        $this->expectException(Throwable::class);

        $this->load($config);
    }

    public function testTheServicesOfTheOrdersLifeComeWithDoctrineAndTheirSettings(): void
    {
        $container = $this->load(['offline_payments' => ['cheque', 'cheque', 'wire'], 'payment_links' => ['default_hours' => 24, 'max_hours' => 48], 'order_expiry' => ['unpaid_hours' => 12, 'delete_after_days' => 7], 'refunds' => ['single_step' => true]], self::DOCTRINE);

        $payments = $container->getDefinition(Payments::class);
        self::assertSame(['cheque', 'wire'], $payments->getArgument('$offlineMethods'));
        self::assertSame('event_dispatcher', (string) $this->namedReference($payments, '$dispatcher'));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->namedReference($payments, '$dispatcher')->getInvalidBehavior());
        self::assertSame('clock', (string) $this->namedReference($payments, '$clock'));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $this->namedReference($payments, '$clock')->getInvalidBehavior());

        $wishLists = $container->getDefinition(WishLists::class);
        self::assertTrue($wishLists->isPublic());
        self::assertTrue($wishLists->isAutowired());
        self::assertSame([['event' => OrderPurchased::class, 'method' => 'recordPurchase']], $wishLists->getTag('kernel.event_listener'));

        $links = $container->getDefinition(PaymentLinks::class);
        self::assertTrue($links->isPublic());
        self::assertTrue($links->isAutowired());
        self::assertSame([24, 48], [$links->getArgument('$defaultHours'), $links->getArgument('$maxHours')]);

        $changes = $container->getDefinition(OrderChanges::class);
        self::assertTrue($changes->isPublic());
        self::assertTrue($changes->isAutowired());

        $expiry = $container->getDefinition(OrderExpiry::class);
        self::assertTrue($expiry->isPublic());
        self::assertTrue($expiry->isAutowired());
        self::assertSame([12, 7], [$expiry->getArgument('$unpaidHours'), $expiry->getArgument('$retentionDays')]);

        $refunds = $container->getDefinition(Refunds::class);
        self::assertTrue($refunds->isPublic());
        self::assertTrue($refunds->isAutowired());
        self::assertTrue($refunds->getArgument('$singleStep'));
        $refundEvents = $refunds->getArgument('$dispatcher');
        self::assertInstanceOf(Reference::class, $refundEvents);
        self::assertSame('event_dispatcher', (string) $refundEvents);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $refundEvents->getInvalidBehavior());

        foreach ([DoctrineWishListRepository::class, DoctrinePaymentLinkRepository::class, DoctrineOrderChangeRepository::class, DoctrineOrderJournal::class, DoctrineRefundRepository::class, DoctrinePaymentLock::class] as $repository) {
            self::assertSame(['doctrine.orm.entity_manager'], $this->references($container->getDefinition($repository)), $repository);
        }

        self::assertSame(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $container->getDefinition(BinaryKeysListener::class)->getTags());
    }

    public function testTheSettingsDefaultToADayAndAMonth(): void
    {
        $container = $this->load([], self::DOCTRINE);

        $links = $container->getDefinition(PaymentLinks::class);
        self::assertSame([72, 720], [$links->getArgument('$defaultHours'), $links->getArgument('$maxHours')]);

        $expiry = $container->getDefinition(OrderExpiry::class);
        self::assertSame([24, 30], [$expiry->getArgument('$unpaidHours'), $expiry->getArgument('$retentionDays')]);
        self::assertFalse($container->getDefinition(Refunds::class)->getArgument('$singleStep'));
        self::assertSame([], $container->getDefinition(Payments::class)->getArgument('$offlineMethods'));

        $core = $container->getDefinition(CoreDocumentTypes::class)->getArguments();
        self::assertSame([], $core[3]);
    }

    public function testADocumentTypeKeepsItsCodeAndItsDigitsDefaultToEight(): void
    {
        $core = $this->load(['document_types' => ['slip_2' => ['prefix' => 'S-']]])->getDefinition(CoreDocumentTypes::class);

        self::assertSame(['slip_2' => ['prefix' => 'S-', 'digits' => 8, 'visibility' => 'hidden']], $core->getArgument(3));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function limits(): iterable
    {
        yield 'one digit' => [['document_types' => ['slip' => ['prefix' => 'S-', 'digits' => 1]]], true];
        yield 'no digit' => [['document_types' => ['slip' => ['prefix' => 'S-', 'digits' => 0]]], false];
        yield 'eighteen digits' => [['document_types' => ['slip' => ['prefix' => 'S-', 'digits' => 18]]], true];
        yield 'nineteen digits' => [['document_types' => ['slip' => ['prefix' => 'S-', 'digits' => 19]]], false];
        yield 'a link of one hour' => [['payment_links' => ['default_hours' => 1, 'max_hours' => 1]], true];
        yield 'a link of no hour' => [['payment_links' => ['default_hours' => 0, 'max_hours' => 1]], false];
        yield 'a link of no most' => [['payment_links' => ['default_hours' => 1, 'max_hours' => 0]], false];
        yield 'a most of a year' => [['payment_links' => ['default_hours' => 1, 'max_hours' => 8_760]], true];
        yield 'a most past a year' => [['payment_links' => ['default_hours' => 1, 'max_hours' => 8_761]], false];
        yield 'a default past its most' => [['payment_links' => ['default_hours' => 49, 'max_hours' => 48]], false];
        yield 'a default at its most' => [['payment_links' => ['default_hours' => 48, 'max_hours' => 48]], true];
        yield 'an expiry of one hour' => [['order_expiry' => ['unpaid_hours' => 1]], true];
        yield 'an expiry of no hour' => [['order_expiry' => ['unpaid_hours' => 0]], false];
        yield 'an expiry of a year' => [['order_expiry' => ['unpaid_hours' => 8_760]], true];
        yield 'an expiry past a year' => [['order_expiry' => ['unpaid_hours' => 8_761]], false];
        yield 'a retention of one day' => [['order_expiry' => ['delete_after_days' => 1]], true];
        yield 'a retention of no day' => [['order_expiry' => ['delete_after_days' => 0]], false];
        yield 'a retention of ten years' => [['order_expiry' => ['delete_after_days' => 3_650]], true];
        yield 'a retention past ten years' => [['order_expiry' => ['delete_after_days' => 3_651]], false];
        yield 'a status of a state' => [['order_statuses' => ['awaiting_pickup' => 'placed']], true];
        yield 'a status of no state' => [['order_statuses' => ['awaiting_pickup' => 'shipped']], false];
        yield 'a method' => [['offline_payments' => ['cheque', 'bank_transfer', 'a'.str_repeat('1', 31)]], true];
        yield 'a method of 33 characters' => [['offline_payments' => ['a'.str_repeat('1', 32)]], false];
        yield 'a method in capitals' => [['offline_payments' => ['Cheque']], false];
        yield 'a method starting with a dash' => [['offline_payments' => ['-cheque']], false];
        yield 'a method ending with a dash' => [['offline_payments' => ['cheque-']], false];
        yield 'a method ending with a line break' => [['offline_payments' => ["cheque\n"]], false];
        yield 'a method starting with a digit' => [['offline_payments' => ['1cheque']], false];
        yield 'a method of a number' => [['offline_payments' => [5]], false];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('limits')]
    public function testEachSettingHasItsBounds(array $config, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        self::assertInstanceOf(ContainerBuilder::class, $this->load($config));
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

    /**
     * @return list<string>
     */
    private function references(Definition $definition): array
    {
        return array_values(array_map(static fn (mixed $argument): string => $argument instanceof Reference ? (string) $argument : '', $definition->getArguments()));
    }

    private function reference(Definition $definition, int $index): Reference
    {
        $argument = $definition->getArgument($index);
        self::assertInstanceOf(Reference::class, $argument);

        return $argument;
    }

    private function namedReference(Definition $definition, string $name): Reference
    {
        $argument = $definition->getArgument($name);
        self::assertInstanceOf(Reference::class, $argument);

        return $argument;
    }

    public function testALinkOpensItsOrderForOneHourAtLeast(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('is too small');

        $this->load(['payment_links' => ['default_hours' => 1, 'max_hours' => 0]]);
    }
}
