<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle;

use LogicException;
use Override;
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
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The promotions of the core of commerce, on the products of the catalogue: a share off, an amount off, N for X, a
 * price for a bundle, free shipping; per channel, customer group, period, products and their exclusions, code, uses in
 * all and per customer, products the customer owns; ordered after one another and kept apart from one another. They
 * run as an `OrderProcessor` of the core, before its taxes, and their free shipping after the delivery charge.
 *
 * The module plugs into `phil/commerce-bundle` and `phil/commerce-catalog-bundle`, and refuses to load without them.
 * With DoctrineBundle, promotions, their translations, codes, redemptions and uses per customer are mapped and stored.
 *
 * ```yaml
 * phil_commerce_promotions:
 *     translations:
 *         locales: { fr_CA: [fr], fr: [en], en: [] }
 * ```
 */
final class PhilCommercePromotionsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_promotions';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('translations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('locales')
                            ->info('The locales the labels are shown in, each with the locales tried after it: {fr_CA: [fr], fr: [en], en: []}. None: every promotion in its own label.')
                            ->useAttributeAsKey('locale')
                            ->normalizeKeys(false)
                            ->arrayPrototype()->scalarPrototype()->cannotBeEmpty()->end()->end()
                            ->defaultValue([])
                            ->validate()
                                ->always(static function (array $locales): array {
                                    try {
                                        DeclaredLocales::fromArray($locales);
                                    } catch (InvalidLocaleDimension $invalidLocaleDimension) {
                                        throw new InvalidConfigurationException('phil_commerce_promotions.translations.locales: '.$invalidLocaleDimension->getMessage(), 0, $invalidLocaleDimension);
                                    }

                                    return $locales;
                                })
                            ->end()
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
        if (!$builder->hasExtension('phil_commerce') || !$builder->hasExtension('phil_commerce_catalog')) {
            throw new LogicException('PhilCommercePromotionsBundle is a module of the core of commerce on its catalogue: register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle, from phil/commerce-catalog-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommercePromotionsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommercePromotionsBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Every service reads the repository: Doctrine's, below. An application storing promotions otherwise declares
        // the services itself, with its own adapter.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        /** @var array{translations: array{locales: array<string, list<string>>}} $config */
        $locales = $config['translations']['locales'];

        if ([] === $locales) {
            $services->set(PromotionTexts::class)->public();
        } else {
            $services->set('phil_commerce_promotions.translations', Translations::class)->args([new Reference('doctrine.orm.entity_manager')]);
            $services->set('phil_commerce_promotions.locales', DeclaredLocales::class)->factory([DeclaredLocales::class, 'fromArray'])->args([$locales]);
            $services->set(PromotionTexts::class)
                ->args([new Reference('phil_commerce_promotions.translations'), new Reference('phil_commerce_promotions.locales'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }

        $services->set(DoctrinePromotionRepository::class)->args([new Reference('doctrine.orm.entity_manager')]);
        $services->alias(PromotionRepository::class, DoctrinePromotionRepository::class)->public();

        $services->set(PromotionEvaluator::class)->args([
            new Reference(PromotionRepository::class),
            new Reference(PromotionTexts::class),
            new Reference(CustomerOwnership::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
        // Before the core's taxes, which tax each line once discounted; before the manual discounts and the delivery
        // charge, which read what the promotions took off.
        $services->set(PromotionProcessor::class)->autowire()->tag('phil_commerce.order_processor', [
            'before' => [TaxOrderProcessor::class, ManualDiscountProcessor::class, FreeShippingProcessor::SHIPPING_PROCESSOR],
        ]);
        // After the delivery charge it waives, before the taxes.
        $services->set(FreeShippingProcessor::class)->tag('phil_commerce.order_processor', [
            'after' => [PromotionProcessor::class, FreeShippingProcessor::SHIPPING_PROCESSOR],
            'before' => TaxOrderProcessor::class,
        ]);
        $services->set(Promotions::class)->args([new Reference(PromotionRepository::class)])->public();
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
