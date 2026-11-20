<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceBundle\Application\ManualDiscountProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommercePromotionsBundle\Application\Contract\CustomerOwnership;
use Phil\CommercePromotionsBundle\Application\FreeShippingProcessor;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Application\PromotionProcessor;
use Phil\CommercePromotionsBundle\Application\Promotions;
use Phil\CommercePromotionsBundle\Application\PromotionTexts;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Infrastructure\Doctrine\DoctrinePromotionRepository;
use Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommercePromotionsBundle::class)]
final class PhilCommercePromotionsBundleTest extends TestCase
{
    public function testItPlugsThePromotionsIntoTheCore(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_promotions', $this->extension()->getAlias());

        $processor = $container->getDefinition(PromotionProcessor::class);
        self::assertTrue($processor->isAutowired());
        self::assertFalse($processor->isPublic());
        self::assertSame([['before' => [TaxOrderProcessor::class, ManualDiscountProcessor::class, FreeShippingProcessor::SHIPPING_PROCESSOR]]], $processor->getTag('phil_commerce.order_processor'));

        $shipping = $container->getDefinition(FreeShippingProcessor::class);
        self::assertSame([['after' => [PromotionProcessor::class, FreeShippingProcessor::SHIPPING_PROCESSOR], 'before' => TaxOrderProcessor::class]], $shipping->getTag('phil_commerce.order_processor'));

        $evaluator = $container->getDefinition(PromotionEvaluator::class)->getArguments();
        self::assertSame([PromotionRepository::class, PromotionTexts::class, CustomerOwnership::class], $this->ids($evaluator));
        self::assertInstanceOf(Reference::class, $evaluator[2]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $evaluator[2]->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $evaluator[1]);
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $evaluator[1]->getInvalidBehavior());

        self::assertTrue($container->getDefinition(Promotions::class)->isPublic());
        self::assertSame([PromotionRepository::class], $this->ids($container->getDefinition(Promotions::class)->getArguments()));
        self::assertSame(DoctrinePromotionRepository::class, (string) $container->getAlias(PromotionRepository::class));
        self::assertTrue($container->getAlias(PromotionRepository::class)->isPublic());
        self::assertSame('doctrine.orm.entity_manager', $this->ids([$container->getDefinition(DoctrinePromotionRepository::class)->getArgument(0)])[0]);
    }

    public function testWithoutLocalesPromotionsKeepTheirOwnLabels(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getDefinition(PromotionTexts::class)->getArguments());
        self::assertTrue($container->getDefinition(PromotionTexts::class)->isPublic());
        self::assertFalse($container->hasDefinition('phil_commerce_promotions.translations'));
    }

    public function testWithLocalesLabelsAreTranslated(): void
    {
        $container = $this->load(['translations' => ['locales' => ['fr' => ['en'], 'en' => []]]]);

        $texts = $container->getDefinition(PromotionTexts::class);
        self::assertTrue($texts->isPublic());
        $arguments = $texts->getArguments();
        self::assertSame(['phil_commerce_promotions.translations', 'phil_commerce_promotions.locales', 'translator'], $this->ids($arguments));
        self::assertInstanceOf(Reference::class, $arguments[2]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());
        self::assertSame(Translations::class, $container->getDefinition('phil_commerce_promotions.translations')->getClass());
        self::assertSame('doctrine.orm.entity_manager', $this->ids([$container->getDefinition('phil_commerce_promotions.translations')->getArgument(0)])[0]);
        $locales = $container->getDefinition('phil_commerce_promotions.locales');
        self::assertSame([DeclaredLocales::class, 'fromArray'], $locales->getFactory());
        self::assertSame([['fr' => ['en'], 'en' => []]], $locales->getArguments());
    }

    public function testAnUnknownLocaleIsRefused(): void
    {
        try {
            $this->load(['translations' => ['locales' => ['fr' => ['xx-not a locale']]]]);
            self::fail('The locales were accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            $previous = $invalidConfigurationException->getPrevious();

            self::assertInstanceOf(InvalidLocaleDimension::class, $previous);
            self::assertSame('phil_commerce_promotions.translations.locales: '.$previous->getMessage(), $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testTheLocaleKeysAreNotNormalized(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"fr-CA" is not a locale');

        $this->load(['translations' => ['locales' => ['fr-CA' => []]]]);
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(PromotionProcessor::class));
        self::assertFalse($container->hasDefinition(PromotionTexts::class));
        self::assertFalse($container->hasAlias(PromotionRepository::class));
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withModules();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommercePromotionsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommercePromotionsBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommercePromotionsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = $this->withModules();
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testItRefusesToPrependWithoutTheCore(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce_catalog'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilCommercePromotionsBundle is a module of the core of commerce on its catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.');

        $this->prepending()->prepend($container);
    }

    public function testItRefusesToPrependWithoutTheCatalogue(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));

        $this->expectException(LogicException::class);

        $this->prepending()->prepend($container);
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

    private function withModules(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));
        $container->registerExtension($this->extensionNamed('phil_commerce_catalog'));

        return $container;
    }

    /**
     * The services and values a definition's arguments name.
     *
     * @param array<array-key, mixed> $arguments
     *
     * @return list<string>
     */
    private function ids(array $arguments): array
    {
        return array_values(array_map(static fn (mixed $argument): string => $argument instanceof Reference || \is_string($argument) ? (string) $argument : get_debug_type($argument), $arguments));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommercePromotionsBundle()->getContainerExtension();
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
