<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle;

use LogicException;
use Override;
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
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Infrastructure\Catalog\CatalogSetParts;
use Phil\CommerceReturnsBundle\Infrastructure\Doctrine\DoctrineReturnRepository;
use Phil\CommerceReturnsBundle\Infrastructure\Logistics\LogisticsShipmentDates;
use Phil\CommerceReturnsBundle\Infrastructure\PagedMedia\PagedMediaReturnSlips;
use Phil\CommerceReturnsBundle\Infrastructure\Promotions\PromotionSpecials;
use Phil\CommerceReturnsBundle\Infrastructure\StoredValue\StoredValueStoreCredit;
use Phil\PagedMedia\Pdf\Document;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Returns, a module of the core of commerce: return requests — RMAs — authorized, sent back for information,
 * received and inspected or expired, the resellable units put back on the shelf, and the value refunded, given as
 * store credit or used for an exchange; their credit notes, their slips and their labels.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. Its services read its storage and
 * the core's orders and payments, Doctrine's all: they are registered with DoctrineBundle only. Each optional
 * module plugs an adapter in when the application registers it: store credit on `phil/commerce-stored-value-bundle`,
 * sets on `phil/commerce-catalog-bundle`, specials on `phil/commerce-promotions-bundle`, shipment dates on
 * `phil/commerce-logistics-bundle`; slips are drawn with `phil/paged-media` when it is installed.
 *
 * ```yaml
 * phil_commerce_returns:
 *     window_days: 30
 *     window_from: shipped
 *     restocking_fee: 150000
 *     delivery_refund: shops_fault
 *     expiry_days: 30
 *     rate_limiter: limiter.returns
 *     slips: { prefix: 'RS-', digits: 8 }
 * ```
 */
final class PhilCommerceReturnsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_returns';

    /**
     * Return slips are drawn when a renderer is there: `phil/paged-media`'s, or the application's own.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition(ReturnSlips::class) && !$container->hasDefinition(ReturnSlipRenderer::class) && !$container->hasAlias(ReturnSlipRenderer::class)) {
                    $container->removeDefinition(ReturnSlips::class);
                }
            }
        });
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('window_days')
                    ->info('How many days after an order is placed — or last shipped — its goods may come back.')
                    ->defaultValue(30)
                    ->min(0)
                ->end()
                ->enumNode('window_from')
                    ->info("What the window counts from: the order's placement, or its last shipment when the logistics module knows one.")
                    ->values(['placed', 'shipped'])
                    ->defaultValue('placed')
                ->end()
                ->integerNode('restocking_fee')
                    ->info('The share kept on the lines the shop is not at fault for, in parts per million: 150000 for 15 %.')
                    ->defaultValue(0)
                    ->min(0)
                    ->max(1_000_000)
                ->end()
                ->enumNode('delivery_refund')
                    ->info('When the delivery charge is given back, once per order: never, with a return the shop is at fault for or the one bringing back every unit, or only with the latter.')
                    ->values(array_map(static fn (DeliveryRefund $policy): string => $policy->value, DeliveryRefund::cases()))
                    ->defaultValue(DeliveryRefund::ShopsFault->value)
                ->end()
                ->integerNode('expiry_days')
                    ->info('How many days an authorized return waits for its goods before Returns::expire() expires it; null for ever.')
                    ->defaultNull()
                    ->min(1)
                ->end()
                ->scalarNode('rate_limiter')
                    ->info('The service of a rate limiter factory of symfony/rate-limiter that bounds the returns each customer asks for; null for none.')
                    ->defaultNull()
                ->end()
                ->arrayNode('slips')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')->info('Printed before the number of each return slip.')->defaultValue('RS-')->end()
                        ->integerNode('digits')->info('The digits each slip number is padded to.')->defaultValue(8)->min(1)->max(18)->end()
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
            throw new LogicException('PhilCommerceReturnsBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceReturnsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceReturnsBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(Restocking::class)->addTag('phil_commerce_returns.restocking');

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        /** @var array{window_days: int, window_from: string, restocking_fee: int, delivery_refund: string, expiry_days: ?int, rate_limiter: ?string, slips: array{prefix: string, digits: int}} $config */
        $services = $container->services();
        $services->set(ReturnsGateway::class)->tag('phil_commerce.payment_gateway');
        $services->set(ReturnDocumentTypes::class)->args([$config['slips']['prefix'], $config['slips']['digits']])->autoconfigure();
        $services->set('phil_commerce_returns.policy', ReturnPolicy::class)
            ->factory([ReturnPolicy::class, 'of'])
            ->args([$config['window_days'], 'shipped' === $config['window_from'], $config['restocking_fee'], DeliveryRefund::from($config['delivery_refund']), $config['expiry_days']]);
        $returns = $services->set(Returns::class)->autowire()->public()
            ->arg('$policy', service('phil_commerce_returns.policy'))
            ->arg('$restocking', tagged_iterator('phil_commerce_returns.restocking'))
            ->arg('$labels', service(ReturnLabels::class)->nullOnInvalid())
            ->arg('$limiter', null === $config['rate_limiter'] ? null : service($config['rate_limiter']))
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid());
        $services->set(ReturnCreditNotes::class)->autowire()->public()
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid());
        $services->set(ReturnSlips::class)->autowire()->public();

        if (class_exists(Document::class)) {
            $services->set(PagedMediaReturnSlips::class)->args([service('translator')->nullOnInvalid()]);
            $services->alias(ReturnSlipRenderer::class, PagedMediaReturnSlips::class);
        }

        // Store credit through the stored-value module, when the application registers it.
        if ($this->hasBundle($builder, 'PhilCommerceStoredValueBundle')) {
            $services->set(StoredValueStoreCredit::class)->autowire();
            $services->alias(StoreCreditGranter::class, StoredValueStoreCredit::class);
            $returns->arg('$storeCredit', service(StoreCreditGranter::class));
        }

        // Sets, specials and shipment dates through the modules that know them, when the application registers them.
        foreach (['PhilCommerceCatalogBundle' => [CatalogSetParts::class, SetParts::class, '$sets'], 'PhilCommercePromotionsBundle' => [PromotionSpecials::class, SpecialDiscounts::class, '$specials'], 'PhilCommerceLogisticsBundle' => [LogisticsShipmentDates::class, ShipmentDates::class, '$shipments']] as $bundle => [$adapter, $port, $argument]) {
            if ($this->hasBundle($builder, $bundle)) {
                $services->set($adapter)->autowire();
                $services->alias($port, $adapter);
                $returns->arg($argument, service($port));
            }
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineReturnRepository::class] as $adapter) {
            $services->set($adapter)->args([service('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
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
