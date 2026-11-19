<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnLabels;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer;
use Phil\CommerceReturnsBundle\Application\Contract\SetParts;
use Phil\CommerceReturnsBundle\Application\Contract\ShipmentDates;
use Phil\CommerceReturnsBundle\Application\Contract\SpecialDiscounts;
use Phil\CommerceReturnsBundle\Application\Port\Restocking;
use Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter;
use Phil\CommerceReturnsBundle\Application\ReturnCreditNotes;
use Phil\CommerceReturnsBundle\Application\ReturnDocumentTypes;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Application\ReturnSlips;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Infrastructure\Catalog\CatalogSetParts;
use Phil\CommerceReturnsBundle\Infrastructure\Doctrine\DoctrineReturnRepository;
use Phil\CommerceReturnsBundle\Infrastructure\Logistics\LogisticsShipmentDates;
use Phil\CommerceReturnsBundle\Infrastructure\PagedMedia\PagedMediaReturnSlips;
use Phil\CommerceReturnsBundle\Infrastructure\Promotions\PromotionSpecials;
use Phil\CommerceReturnsBundle\Infrastructure\StoredValue\StoredValueStoreCredit;
use Phil\CommerceReturnsBundle\PhilCommerceReturnsBundle;
use Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle;
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

#[CoversClass(PhilCommerceReturnsBundle::class)]
final class PhilCommerceReturnsBundleTest extends TestCase
{
    public function testItWiresReturns(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_returns', $this->extension()->getAlias());
        $returns = $container->getDefinition(Returns::class);
        self::assertTrue($returns->isPublic());
        self::assertTrue($returns->isAutowired());
        self::assertSame([30, false, 0, DeliveryRefund::ShopsFault, null], $container->getDefinition('phil_commerce_returns.policy')->getArguments());
        self::assertSame([ReturnPolicy::class, 'of'], $container->getDefinition('phil_commerce_returns.policy')->getFactory());
        self::assertSame('phil_commerce_returns.policy', $this->referenced($returns->getArgument('$policy')));
        self::assertNull($returns->getArgument('$limiter'));

        foreach (['$labels' => ReturnLabels::class, '$dispatcher' => 'event_dispatcher'] as $argument => $id) {
            $reference = $returns->getArgument($argument);
            self::assertInstanceOf(Reference::class, $reference);
            self::assertSame([$id, ContainerInterface::NULL_ON_INVALID_REFERENCE], [(string) $reference, $reference->getInvalidBehavior()]);
        }

        $notes = $container->getDefinition(ReturnCreditNotes::class);
        self::assertTrue($notes->isPublic());
        self::assertTrue($notes->isAutowired());
        self::assertSame('event_dispatcher', $this->referenced($notes->getArgument('$dispatcher')));
        self::assertTrue($container->getDefinition(ReturnSlips::class)->isPublic());
        self::assertSame(PagedMediaReturnSlips::class, (string) $container->getAlias(ReturnSlipRenderer::class));
        self::assertSame('translator', $this->referenced($container->getDefinition(PagedMediaReturnSlips::class)->getArgument(0)));
        self::assertSame(['RS-', 8], $container->getDefinition(ReturnDocumentTypes::class)->getArguments());
        self::assertTrue($container->getDefinition(ReturnDocumentTypes::class)->isAutoconfigured());

        foreach ([CatalogSetParts::class, PromotionSpecials::class, LogisticsShipmentDates::class] as $adapter) {
            self::assertFalse($container->hasDefinition($adapter));
        }

        $restocking = $returns->getArgument('$restocking');
        self::assertInstanceOf(TaggedIteratorArgument::class, $restocking);
        self::assertSame('phil_commerce_returns.restocking', $restocking->getTag());
        self::assertSame([[]], $container->getDefinition(ReturnsGateway::class)->getTag('phil_commerce.payment_gateway'));
        self::assertFalse($container->hasDefinition(StoredValueStoreCredit::class));
        self::assertArrayNotHasKey('$storeCredit', $returns->getArguments());
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load(['window_days' => 0, 'window_from' => 'shipped', 'restocking_fee' => 1_000_000, 'delivery_refund' => 'whole_order', 'expiry_days' => 1, 'rate_limiter' => 'limiter.returns', 'slips' => ['prefix' => 'RET-', 'digits' => 18]]);

        self::assertSame([0, true, 1_000_000, DeliveryRefund::WholeOrder, 1], $container->getDefinition('phil_commerce_returns.policy')->getArguments());
        self::assertSame('limiter.returns', $this->referenced($container->getDefinition(Returns::class)->getArgument('$limiter')));
        self::assertSame(['RET-', 18], $container->getDefinition(ReturnDocumentTypes::class)->getArguments());
        self::assertSame(DeliveryRefund::Never, $this->load(['delivery_refund' => 'never'])->getDefinition('phil_commerce_returns.policy')->getArgument(3));
    }

    public function testTheLowestValuesAreAccepted(): void
    {
        $container = $this->load(['window_days' => 0, 'window_from' => 'placed', 'restocking_fee' => 0, 'expiry_days' => 1, 'slips' => ['digits' => 1]]);

        self::assertSame([0, false, 0, DeliveryRefund::ShopsFault, 1], $container->getDefinition('phil_commerce_returns.policy')->getArguments());
        self::assertSame(['RS-', 1], $container->getDefinition(ReturnDocumentTypes::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'a negative window' => [['window_days' => -1]];
        yield 'a negative fee' => [['restocking_fee' => -1]];
        yield 'more than everything' => [['restocking_fee' => 1_000_001]];
        yield 'an unknown start of the window' => [['window_from' => 'delivered']];
        yield 'an unknown delivery refund' => [['delivery_refund' => 'sometimes']];
        yield 'an expiry of no day' => [['expiry_days' => 0]];
        yield 'no digit' => [['slips' => ['digits' => 0]]];
        yield 'too many digits' => [['slips' => ['digits' => 19]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    public function testWithTheStoredValueModuleStoreCreditIsWired(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class, 'PhilCommerceStoredValueBundle' => PhilCommerceStoredValueBundle::class]);

        self::assertTrue($container->getDefinition(StoredValueStoreCredit::class)->isAutowired());
        self::assertSame(StoredValueStoreCredit::class, (string) $container->getAlias(StoreCreditGranter::class));
        $argument = $container->getDefinition(Returns::class)->getArgument('$storeCredit');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(StoreCreditGranter::class, (string) $argument);
    }

    public function testTheModulesThatKnowSetsSpecialsAndShipmentsPlugTheirAdapters(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class, 'PhilCommerceCatalogBundle' => PhilCommerceCatalogBundle::class, 'PhilCommercePromotionsBundle' => PhilCommercePromotionsBundle::class, 'PhilCommerceLogisticsBundle' => PhilCommerceLogisticsBundle::class]);
        $returns = $container->getDefinition(Returns::class);

        foreach (['$sets' => [CatalogSetParts::class, SetParts::class], '$specials' => [PromotionSpecials::class, SpecialDiscounts::class], '$shipments' => [LogisticsShipmentDates::class, ShipmentDates::class]] as $argument => [$adapter, $port]) {
            self::assertTrue($container->getDefinition($adapter)->isAutowired());
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertSame($port, $this->referenced($returns->getArgument($argument)));
        }
    }

    public function testWithoutARendererNoSlipIsPrinted(): void
    {
        $container = $this->load([]);
        $container->removeAlias(ReturnSlipRenderer::class);
        new PhilCommerceReturnsBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if (str_starts_with($pass::class, 'Symfony\\Component\\DependencyInjection\\Compiler\\CompilerPassInterface@anonymous')) {
                $pass->process($container);
            }
        }

        self::assertFalse($container->hasDefinition(ReturnSlips::class));

        $kept = $this->load([]);
        new PhilCommerceReturnsBundle()->build($kept);

        foreach ($kept->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if (str_starts_with($pass::class, 'Symfony\\Component\\DependencyInjection\\Compiler\\CompilerPassInterface@anonymous')) {
                $pass->process($kept);
            }
        }

        self::assertTrue($kept->hasDefinition(ReturnSlips::class));
    }

    public function testARendererDefinedByTheApplicationKeepsTheSlips(): void
    {
        $container = $this->load([]);
        $container->removeAlias(ReturnSlipRenderer::class);
        $container->setDefinition(ReturnSlipRenderer::class, new Definition(PagedMediaReturnSlips::class));
        new PhilCommerceReturnsBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if (str_starts_with($pass::class, 'Symfony\\Component\\DependencyInjection\\Compiler\\CompilerPassInterface@anonymous')) {
                $pass->process($container);
            }
        }

        self::assertTrue($container->hasDefinition(ReturnSlips::class));
    }

    public function testRestockingIsTaggedByAutoconfiguration(): void
    {
        self::assertSame(['phil_commerce_returns.restocking' => [[]]], $this->load([], [])->getAutoconfiguredInstanceof()[Restocking::class]->getTags());
    }

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load([]);

        self::assertSame(DoctrineReturnRepository::class, (string) $container->getAlias(ReturnRepository::class));
        self::assertTrue($container->getAlias(ReturnRepository::class)->isPublic());
        $argument = $container->getDefinition(DoctrineReturnRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceReturnsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceReturnsBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceReturnsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], ['PhilCommerceStoredValueBundle' => PhilCommerceStoredValueBundle::class]);

        foreach ([Returns::class, ReturnsGateway::class, StoredValueStoreCredit::class, DoctrineReturnRepository::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceReturnsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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
        $extension = new PhilCommerceReturnsBundle()->getContainerExtension();
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

    private function referenced(mixed $argument): string
    {
        self::assertInstanceOf(Reference::class, $argument);

        return (string) $argument;
    }
}
