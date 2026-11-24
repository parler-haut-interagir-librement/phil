<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle;

use LogicException;
use Override;
use Phil\CommerceConsignmentBundle\Application\ConfiguredIssueOrder;
use Phil\CommerceConsignmentBundle\Application\ConsignmentStock;
use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Application\Contract\IssueOrderPolicy;
use Phil\CommerceConsignmentBundle\Application\Export\StatementCsv;
use Phil\CommerceConsignmentBundle\Application\Export\StatementView;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceConsignmentBundle\Application\UnsoldStock;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Service\StandardMargins;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineLedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\DoctrineStatementNumbering;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsShippedUnits;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\LogisticsStockMovements;
use Phil\CommerceConsignmentBundle\Infrastructure\Logistics\UnitsShippedListener;
use Phil\CommerceConsignmentBundle\Infrastructure\Messenger\RecordShipmentHandler;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Stock the shop owns and stock left on consignment, side by side on one SKU, as a module of `phil/commerce-bundle` on
 * the stock of `phil/commerce-logistics-bundle`.
 *
 * ```yaml
 * phil_commerce_consignment:
 *     issue_order: oldest_first          # owned_first, consigned_first or oldest_first
 *     issue_order_by_sku:
 *         BOOK-123: consigned_first
 *     statement_number_prefix: ST-       # the statements' series: ST-00000001, ST-00000002…
 *     record_shipments: true             # sales written when the logistics module announces UnitsShipped
 * ```
 */
final class PhilCommerceConsignmentBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_consignment';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $orders = array_map(static fn (IssueOrder $order): string => $order->value, IssueOrder::cases());

        $definition->rootNode()
            ->children()
                ->enumNode('issue_order')
                    ->info('Which units of a SKU leave first when the shop holds some it bought and some on consignment; within each group, the oldest receipt first.')
                    ->values($orders)
                    ->defaultValue(IssueOrder::OldestFirst->value)
                ->end()
                ->arrayNode('issue_order_by_sku')
                    ->info('Another issue order for the SKUs named: SKU → owned_first, consigned_first or oldest_first.')
                    ->useAttributeAsKey('sku')
                    ->normalizeKeys(false)
                    ->enumPrototype()->values($orders)->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('statement_number_prefix')
                    ->info("The prefix of the statements' numbers, before eight digits: up to 24 letters, digits, dots, dashes, slashes or hashes.")
                    ->defaultValue('ST-')
                    ->validate()
                        ->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match('/^[A-Za-z0-9.#\/-]{0,24}$/D', $prefix))
                        ->thenInvalid('A statement prefix has up to 24 letters, digits, dots, dashes, slashes or hashes; %s does not.')
                    ->end()
                ->end()
                ->booleanNode('record_shipments')
                    ->info('Writes the sales of an order to the ledger when the logistics module announces UnitsShipped, in the transaction of the shipment; false to call ShippedOrders or dispatch RecordShipment instead.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * Registers the ledger's tables with Doctrine. The bundle is a module of the core of commerce, on the stock of the
     * logistics module: both are required.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce') || !$builder->hasExtension('phil_commerce_logistics')) {
            throw new LogicException('PhilCommerceConsignmentBundle splits the stock of phil/commerce-logistics-bundle by owner: register PhilCommerceBundle and PhilCommerceLogisticsBundle too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceConsignmentBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceConsignmentBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        /** @var string $default */
        $default = $config['issue_order'];
        /** @var array<string, string> $bySku */
        $bySku = $config['issue_order_by_sku'];
        $services->set(ConfiguredIssueOrder::class)
            ->factory(ConfiguredIssueOrder::fromConfiguration(...))
            ->args([$default, $bySku]);
        $services->alias(IssueOrderPolicy::class, ConfiguredIssueOrder::class)->public();

        // Every other service reads the ledger's repository: Doctrine's, below. An application storing the ledger
        // otherwise declares the services itself, with its own adapter.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(BinaryKeysListener::class)->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        $services->set(Ledger::class)->autowire();
        $services->set(ConsignmentStock::class)->autowire()->public();
        $services->set(ShippedOrders::class)->autowire()->public();
        $services->set(ConsignorAgreements::class)->autowire()->public();
        $services->set(ConsignorStatements::class)->autowire()->public();
        $services->set(ConsignorPayouts::class)->autowire()->public();
        $services->set(UnsoldStock::class)->autowire()->public();
        $services->set(StatementCsv::class)->public();
        $services->set(StatementView::class)->public();

        if (true === $config['record_shipments']) {
            $services->set(UnitsShippedListener::class)->autowire()->tag('kernel.event_listener', ['event' => UnitsShipped::class]);
        }

        if (interface_exists(MessageBusInterface::class)) {
            $services->set(RecordShipmentHandler::class)->autowire()->tag('messenger.message_handler');
        }

        // The ports by reflection: the bundle class wires adapters, and names no port.
        $services->set(DoctrineLedgerRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $this->aliasPorts($container, DoctrineLedgerRepository::class);
        $services->set(DoctrineConsignorRepository::class)->args([service('doctrine.orm.entity_manager')]);
        $this->aliasPorts($container, DoctrineConsignorRepository::class);
        /** @var string $prefix */
        $prefix = $config['statement_number_prefix'];
        $services->set(DoctrineStatementNumbering::class)->args([service('doctrine.orm.entity_manager'), $prefix]);
        $this->aliasPorts($container, DoctrineStatementNumbering::class);

        foreach ([StandardMargins::class, LogisticsStockMovements::class, LogisticsShippedUnits::class] as $adapter) {
            $services->set($adapter)->autowire();
            $this->aliasPorts($container, $adapter);
        }
    }

    /**
     * @param class-string $adapter
     */
    private function aliasPorts(ContainerConfigurator $container, string $adapter): void
    {
        foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
            $container->services()->alias($port, $adapter)->public();
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
