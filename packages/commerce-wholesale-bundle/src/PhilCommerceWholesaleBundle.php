<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle;

use LogicException;
use Override;
use Phil\CommerceWholesaleBundle\Application\AccountGateway;
use Phil\CommerceWholesaleBundle\Application\CreditSales;
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Application\GroupPrices;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Infrastructure\Doctrine\DoctrineWholesaleRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Logistics\CreditShipmentAuthorization;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantityValidator;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\QuantityGuard;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Wholesale, a module of the core of commerce: customer groups as the model of wholesale selling — a share off the
 * base prices, a minimum order — and the buyers' memberships, an account or a tenant each. The group's price is the
 * core's `UnitPriceResolver` after the catalogue's price books, which remain a group's own price list. Quantity rules
 * per SKU and group are a constraint of the Validator, which a resolver asked before every price checks on each line.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. With DoctrineBundle, groups and
 * memberships are mapped and stored.
 */
final class PhilCommerceWholesaleBundle extends AbstractBundle
{
    /** The catalogue's unit price resolver for price books, which the group prices are asked after. */
    private const string PRICE_BOOK_PRICES = 'Phil\\CommerceCatalogBundle\\Application\\PriceBookPrices';

    /** The port of the logistics, named by its interface without depending on the logistics. */
    private const string SHIPMENT_AUTHORIZATION = 'Phil\\CommerceLogisticsBundle\\Application\\Contract\\ShipmentAuthorization';

    private const string SHIPMENT_AUTHORIZATION_TAG = 'phil_commerce_logistics.shipment_authorization';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_wholesale';

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension;
     * each extension then loads in a container of its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce')) {
            throw new LogicException('PhilCommerceWholesaleBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceWholesaleBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceWholesaleBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Every service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        // A unit price resolver after the catalogue's price books, named by their class without depending on the
        // catalogue: the constraint is ignored when the catalogue is not installed.
        $services->set(GroupPrices::class)->autowire()->tag('phil_commerce.unit_price_resolver', ['after' => self::PRICE_BOOK_PRICES]);
        // The quantity rules are checked before any price is asked, the guard giving none: a refused quantity leaves the
        // order as it was.
        $services->set(QuantityGuard::class)->autowire()->tag('phil_commerce.unit_price_resolver', ['before' => [self::PRICE_BOOK_PRICES, GroupPrices::class]]);
        $services->set(AllowedQuantityValidator::class)->autowire()->tag('validator.constraint_validator');
        $services->set(QuantityRules::class)->autowire()->public();
        $services->set(CustomerGroups::class)->autowire()->public();
        $services->set(CreditSales::class)->autowire()->public();
        $services->set(AccountGateway::class)->autowire()->tag('phil_commerce.payment_gateway');

        // With the logistics, a buyer on credit may receive an order before paying it, within the account's credit.
        if (interface_exists(self::SHIPMENT_AUTHORIZATION)) {
            $services->set(CreditShipmentAuthorization::class)->autowire()->tag(self::SHIPMENT_AUTHORIZATION_TAG);
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineWholesaleRepository::class] as $adapter) {
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
