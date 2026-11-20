<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceStoredValueBundle\Application\GiftCards;
use Phil\CommerceStoredValueBundle\Application\GiftCardSellables;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Application\StoredValueGateway;
use Phil\CommerceStoredValueBundle\Application\StoredValuePayments;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceStoredValueBundle::class)]
final class PhilCommerceStoredValueBundleTest extends TestCase
{
    public function testItWiresStoredValue(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_stored_value', $this->extension()->getAlias());

        foreach ([GiftCards::class, StoreCredits::class, StoredValuePayments::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        $gateway = $container->getDefinition(StoredValueGateway::class);
        self::assertTrue($gateway->isAutowired());
        self::assertSame([[]], $gateway->getTag('phil_commerce.payment_gateway'));
    }

    public function testTheGiftCardDefaults(): void
    {
        $sellables = $this->load([])->getDefinition(GiftCardSellables::class);

        self::assertSame(['Gift card', 'exempt', 100_000], $sellables->getArguments());
        self::assertSame([[]], $sellables->getTag('phil_commerce.sellable_resolver'));
    }

    public function testTheGiftCardIsConfigured(): void
    {
        $sellables = $this->load(['gift_card' => ['label' => 'Carte-cadeau', 'tax_category' => 'zero', 'max_value' => 1]])->getDefinition(GiftCardSellables::class);

        self::assertSame(['Carte-cadeau', 'zero', 1], $sellables->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'an empty label' => [['gift_card' => ['label' => '']]];
        yield 'an empty tax category' => [['gift_card' => ['tax_category' => '']]];
        yield 'no value' => [['gift_card' => ['max_value' => 0]]];
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

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load([]);

        self::assertSame(DoctrineStoredValueRepository::class, (string) $container->getAlias(InstrumentRepository::class));
        self::assertTrue($container->getAlias(InstrumentRepository::class)->isPublic());
        $argument = $container->getDefinition(DoctrineStoredValueRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceStoredValueBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceStoredValueBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceStoredValueBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineOnlyTheSellableIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertTrue($container->hasDefinition(GiftCardSellables::class));

        foreach ([GiftCards::class, StoreCredits::class, StoredValuePayments::class, StoredValueGateway::class, DoctrineStoredValueRepository::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }

        self::assertFalse($container->hasAlias(InstrumentRepository::class));
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceStoredValueBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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
        $extension = new PhilCommerceStoredValueBundle()->getContainerExtension();
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
