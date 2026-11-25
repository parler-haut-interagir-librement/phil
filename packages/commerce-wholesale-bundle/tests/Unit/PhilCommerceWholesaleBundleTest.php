<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceWholesaleBundle\Application\AccountGateway;
use Phil\CommerceWholesaleBundle\Application\CreditSales;
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Application\GroupPrices;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;
use Phil\CommerceWholesaleBundle\Domain\Repository\QuantityRuleRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Doctrine\DoctrineWholesaleRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Logistics\CreditShipmentAuthorization;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantityValidator;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\QuantityGuard;
use Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceWholesaleBundle::class)]
final class PhilCommerceWholesaleBundleTest extends TestCase
{
    public function testItWiresGroupsAndTheirPrices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_wholesale', $this->extension()->getAlias());
        self::assertTrue($container->getDefinition(CustomerGroups::class)->isPublic());
        self::assertTrue($container->getDefinition(CustomerGroups::class)->isAutowired());
        $prices = $container->getDefinition(GroupPrices::class);
        self::assertTrue($prices->isAutowired());
        self::assertSame([['after' => 'Phil\\CommerceCatalogBundle\\Application\\PriceBookPrices']], $prices->getTag('phil_commerce.unit_price_resolver'));
    }

    public function testItWiresTheQuantityRules(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getDefinition(QuantityRules::class)->isPublic());
        self::assertTrue($container->getDefinition(QuantityRules::class)->isAutowired());
        self::assertTrue($container->getDefinition(AllowedQuantityValidator::class)->isAutowired());
        self::assertSame([[]], $container->getDefinition(AllowedQuantityValidator::class)->getTag('validator.constraint_validator'));
        $guard = $container->getDefinition(QuantityGuard::class);
        self::assertTrue($guard->isAutowired());
        self::assertSame([['before' => ['Phil\\CommerceCatalogBundle\\Application\\PriceBookPrices', GroupPrices::class]]], $guard->getTag('phil_commerce.unit_price_resolver'));
        self::assertSame(DoctrineWholesaleRepository::class, (string) $container->getAlias(QuantityRuleRepository::class));
        self::assertFalse($this->load([], [])->hasDefinition(QuantityGuard::class));
        self::assertDirectoryExists(new PhilCommerceWholesaleBundle()->getPath().'/translations');
    }

    public function testItWiresSalesOnCredit(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getDefinition(CreditSales::class)->isPublic());
        self::assertTrue($container->getDefinition(CreditSales::class)->isAutowired());
        self::assertTrue($container->getDefinition(AccountGateway::class)->isAutowired());
        self::assertSame([[]], $container->getDefinition(AccountGateway::class)->getTag('phil_commerce.payment_gateway'));
        self::assertSame(DoctrineWholesaleRepository::class, (string) $container->getAlias(CreditRepository::class));
        self::assertFalse($this->load([], [])->hasDefinition(CreditSales::class));
        self::assertTrue($container->getDefinition(CreditShipmentAuthorization::class)->isAutowired());
        self::assertSame([[]], $container->getDefinition(CreditShipmentAuthorization::class)->getTag('phil_commerce_logistics.shipment_authorization'));
        self::assertFalse($this->load([], [])->hasDefinition(CreditShipmentAuthorization::class));
    }

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        foreach ([CustomerGroupRepository::class => DoctrineWholesaleRepository::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
            $argument = $container->getDefinition($adapter)->getArgument(0);
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        }
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceWholesaleBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceWholesaleBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceWholesaleBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(CustomerGroups::class));
        self::assertFalse($container->hasAlias(CustomerGroupRepository::class));
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceWholesaleBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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
        $extension = new PhilCommerceWholesaleBundle()->getContainerExtension();
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
}
