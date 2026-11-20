<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle;

use LogicException;
use Override;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\GiftCardSellables;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Application\StoredValueGateway;
use Phil\CommerceStoredValueBundle\Application\StoredValuePayments;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Stored value, a module of the core of commerce: digital gift cards — sold as a sellable, issued with a code, loaded,
 * voided — and customers' store credit, both spent as payments of the core, alone or beside another method.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. With DoctrineBundle, instruments and
 * their movements are mapped and stored.
 */
final class PhilCommerceStoredValueBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_stored_value';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('gift_card')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('label')
                            ->info('How a gift card line reads in an order, before its value.')
                            ->defaultValue('Gift card')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('tax_category')
                            ->info('The tax category of a gift card sold: in most places, selling one is not taxed.')
                            ->defaultValue('exempt')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_value')
                            ->info('The highest value a gift card is sold at, in minor units.')
                            ->defaultValue(100_000)
                            ->min(1)
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
            throw new LogicException('PhilCommerceStoredValueBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceStoredValueBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceStoredValueBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $giftCard = \is_array($config['gift_card'] ?? null) ? $config['gift_card'] : [];

        $services->set(GiftCardSellables::class)
            ->args([$giftCard['label'] ?? null, $giftCard['tax_category'] ?? null, $giftCard['max_value'] ?? null])
            ->tag('phil_commerce.sellable_resolver');

        // Every other service reads a repository: Doctrine's, below — and the core's payments are stored with
        // Doctrine too. An application storing the module's data otherwise declares the services itself.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(StoredValueGateway::class)->autowire()->tag('phil_commerce.payment_gateway');

        foreach ([GiftCards::class, StoreCredits::class, StoredValuePayments::class] as $service) {
            $services->set($service)->autowire()->public();
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineStoredValueRepository::class] as $adapter) {
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
