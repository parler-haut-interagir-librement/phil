<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceConsignmentBundle\Application\ConfiguredIssueOrder;
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Application\Contract\IssueOrderPolicy;
use Phil\CommerceConsignmentBundle\Application\Contract\ShippedUnits;
use Phil\CommerceConsignmentBundle\Application\Contract\StatementNumbering;
use Phil\CommerceConsignmentBundle\Application\Contract\StockMovements;
use Phil\CommerceConsignmentBundle\Application\Export\StatementCsv;
use Phil\CommerceConsignmentBundle\Application\Export\StatementView;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceConsignmentBundle\Application\UnsoldStock;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Service\StandardMargins;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsShippedUnits;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsStockMovements;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\UnitsShippedListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Messenger\RecordShipmentHandler;
use Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceConsignmentBundle::class)]
final class PhilCommerceConsignmentBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_commerce_consignment', $this->extension()->getAlias());
    }

    public function testTheIssueOrderIsOldestFirstByDefault(): void
    {
        $container = $this->load([], doctrine: false);

        self::assertSame(['oldest_first', []], $container->getDefinition(ConfiguredIssueOrder::class)->getArguments());
        self::assertSame([ConfiguredIssueOrder::class, 'fromConfiguration'], $container->getDefinition(ConfiguredIssueOrder::class)->getFactory());
        self::assertSame(ConfiguredIssueOrder::class, (string) $container->getAlias(IssueOrderPolicy::class));
        self::assertTrue($container->getAlias(IssueOrderPolicy::class)->isPublic());
        self::assertFalse($container->hasDefinition(Ledger::class));
        self::assertFalse($container->hasAlias(LedgerRepository::class));
    }

    public function testTheIssueOrderIsConfiguredWithSkusKeptAsTheyAre(): void
    {
        $container = $this->load([['issue_order' => 'owned_first', 'issue_order_by_sku' => ['Book-1' => 'consigned_first', 'pen_2' => 'oldest_first']]]);

        self::assertSame(['owned_first', ['Book-1' => 'consigned_first', 'pen_2' => 'oldest_first']], $container->getDefinition(ConfiguredIssueOrder::class)->getArguments());
    }

    public function testAnUnknownIssueOrderIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([['issue_order' => 'newest_first']]);
    }

    public function testWithDoctrineEveryServiceAndPortIsWired(): void
    {
        $container = $this->load([]);

        foreach ([ConsignmentStock::class, ShippedOrders::class, ConsignorAgreements::class, ConsignorStatements::class, ConsignorPayouts::class, UnsoldStock::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
            self::assertTrue($container->getDefinition($public)->isAutowired(), $public);
        }

        self::assertTrue($container->getDefinition(Ledger::class)->isAutowired());
        self::assertSame([['event' => 'loadClassMetadata']], $container->getDefinition(BinaryKeysListener::class)->getTag('doctrine.event_listener'));
        self::assertTrue($container->getDefinition(RecordShipmentHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(RecordShipmentHandler::class)->isAutowired());
        self::assertEquals(new Reference('doctrine.orm.entity_manager'), $container->getDefinition(DoctrineLedgerRepository::class)->getArgument(0));
        self::assertEquals(new Reference('doctrine.orm.entity_manager'), $container->getDefinition(DoctrineConsignorRepository::class)->getArgument(0));
        self::assertEquals([new Reference('doctrine.orm.entity_manager'), 'ST-'], $container->getDefinition(DoctrineStatementNumbering::class)->getArguments());

        foreach ([StatementCsv::class, StatementView::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        foreach ([
            LedgerRepository::class => DoctrineLedgerRepository::class,
            MarginCalculator::class => StandardMargins::class,
            StockMovements::class => LogisticsStockMovements::class,
            ShippedUnits::class => LogisticsShippedUnits::class,
            ConsignorRepository::class => DoctrineConsignorRepository::class,
            StatementNumbering::class => DoctrineStatementNumbering::class,
        ] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port), $port);
            self::assertTrue($container->getAlias($port)->isPublic(), $port);
            self::assertTrue($container->getDefinition($adapter)->isAutowired() || str_starts_with($adapter, 'Phil\\CommerceConsignmentBundle\\Infrastructure\\Doctrine\\'), $adapter);
        }

        self::assertFalse($this->load([], doctrine: false)->hasDefinition(ConsignorStatements::class));
    }

    public function testTheSalesOfShippedUnitsAreWrittenOnTheLogisticsEventUnlessTurnedOff(): void
    {
        $definition = $this->load([])->getDefinition(UnitsShippedListener::class);

        self::assertSame([['event' => UnitsShipped::class]], $definition->getTag('kernel.event_listener'));
        self::assertTrue($definition->isAutowired());
        self::assertFalse($this->load([['record_shipments' => false]])->hasDefinition(UnitsShippedListener::class));
        self::assertFalse($this->load([], doctrine: false)->hasDefinition(UnitsShippedListener::class));
    }

    public function testTheStatementPrefixIsConfigured(): void
    {
        self::assertSame('2026/C-', $this->load([['statement_number_prefix' => '2026/C-']])->getDefinition(DoctrineStatementNumbering::class)->getArgument(1));
        self::assertSame('', $this->load([['statement_number_prefix' => '']])->getDefinition(DoctrineStatementNumbering::class)->getArgument(1));

        foreach (['ST ', str_repeat('S', 25), "ST-\n", 12] as $prefix) {
            try {
                $this->load([['statement_number_prefix' => $prefix]]);
                self::fail((string) $prefix);
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString('A statement prefix has up to 24 letters, digits, dots, dashes, slashes or hashes', $invalid->getMessage());
            }
        }
    }

    public function testTheBundleNeedsTheCoreAndTheLogisticsModule(): void
    {
        $container = $this->container(doctrine: true);
        $container->registerExtension($this->extensionOf(new PhilCommerceBundle()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('register PhilCommerceBundle and PhilCommerceLogisticsBundle too.');
        $this->prepend($container);
    }

    public function testTheBundleNeedsTheCore(): void
    {
        $container = $this->container(doctrine: true);
        $container->registerExtension($this->extensionOf(new PhilCommerceLogisticsBundle()));

        $this->expectException(LogicException::class);
        $this->prepend($container);
    }

    public function testTheTablesAreMappedWithDoctrine(): void
    {
        $container = $this->modules(doctrine: true);
        $container->registerExtension($this->extensionOf(new DoctrineBundle()));
        $this->prepend($container);

        $config = $container->getExtensionConfig('doctrine')[0] ?? null;
        self::assertIsArray($config);
        self::assertIsArray($config['orm'] ?? null);
        self::assertIsArray($config['orm']['mappings'] ?? null);
        $mapping = $config['orm']['mappings']['PhilCommerceConsignmentBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Phil\CommerceConsignmentBundle\Domain\Model', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);
    }

    public function testNothingIsMappedWithoutDoctrine(): void
    {
        $container = $this->modules(doctrine: false);
        $this->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs, bool $doctrine = true): ContainerBuilder
    {
        $container = $this->container($doctrine);
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function container(bool $doctrine): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $doctrine ? ['DoctrineBundle' => DoctrineBundle::class] : []);

        return $container;
    }

    private function modules(bool $doctrine): ContainerBuilder
    {
        $container = $this->container($doctrine);
        $container->registerExtension($this->extensionOf(new PhilCommerceBundle()));
        $container->registerExtension($this->extensionOf(new PhilCommerceLogisticsBundle()));

        return $container;
    }

    private function prepend(ContainerBuilder $container): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
    }

    private function extension(): ExtensionInterface
    {
        return $this->extensionOf(new PhilCommerceConsignmentBundle());
    }

    private function extensionOf(BundleInterface $bundle): ExtensionInterface
    {
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
