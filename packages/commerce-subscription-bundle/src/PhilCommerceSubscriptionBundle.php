<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle;

use LogicException;
use Override;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\ProrationOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Seats;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineGiftRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Subscriptions, a module of the core of commerce: offers in tiers of a family, billing periods counted from an
 * anchor, trials, renewal by an order of the core, dunning after a failed payment, cancellation at the end of the
 * paid period, changes of tier at renewal or at once with proration, gifts, and the features each tier unlocks, to a
 * subscriber or to the members of an organization.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it: offers are sold through the core's
 * `SellableResolver`, under the reference type `offer`. With DoctrineBundle, offers and subscriptions are mapped and
 * stored.
 */
final class PhilCommerceSubscriptionBundle extends AbstractBundle
{
    /** The catalogue module's promotions, which the proration runs after when installed. */
    private const string PROMOTION_PROCESSOR = 'Phil\\CommerceCatalogBundle\\Application\\PromotionProcessor';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_subscription';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('payment_attempts')
                    ->info('How many failed payments of a renewal end a subscription; it stays past due, with access, until then.')
                    ->defaultValue(3)
                    ->min(1)
                    ->max(10)
                ->end()
                ->integerNode('abandon_pending_after_days')
                    ->info('How many days a subscription whose first period was never paid stays pending before the sweep ends it.')
                    ->defaultValue(7)
                    ->min(1)
                    ->max(365)
                ->end()
                ->booleanNode('immediate_downgrades')
                    ->info('Whether a change to a lower tier asked at once takes effect at once, prorated, rather than at the next renewal.')
                    ->defaultFalse()
                ->end()
                ->integerNode('gift_redeem_within_days')
                    ->info('How many days the code of a paid gift redeems it.')
                    ->defaultValue(365)
                    ->min(1)
                    ->max(3650)
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
            throw new LogicException('PhilCommerceSubscriptionBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceSubscriptionBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceSubscriptionBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{payment_attempts: int, abandon_pending_after_days: int, immediate_downgrades: bool, gift_redeem_within_days: int} $config */
        $services = $container->services();

        // Every service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        // Tagged by the core's autoconfiguration, as a sellable resolver of the type `offer`.
        $services->set(OfferSellables::class)->autowire()->autoconfigure();
        $services->set(Subscriptions::class)
            ->autowire()
            ->arg('$paymentAttempts', $config['payment_attempts'])
            ->arg('$abandonPendingAfterDays', $config['abandon_pending_after_days'])
            ->arg('$access', service(FeatureAccess::class))
            ->arg('$immediateDowngrades', $config['immediate_downgrades'])
            ->public();
        $services->set(Gifts::class)
            ->autowire()
            ->arg('$redeemWithinDays', $config['gift_redeem_within_days'])
            ->arg('$access', service(FeatureAccess::class))
            ->public();
        $services->set(Seats::class)->autowire()->arg('$access', service(FeatureAccess::class))->public();
        // The application's membership of organizations, when it declares one.
        $services->set(FeatureAccess::class)
            ->autowire()
            ->arg('$organizations', service(OrganizationMembership::class)->nullOnInvalid())
            ->arg('$clock', service('clock')->nullOnInvalid())
            ->public()
            ->tag('kernel.reset', ['method' => 'reset']);
        // After the options and the promotions, before the taxes, which tax the line once prorated.
        $services->set(ProrationOrderProcessor::class)
            ->autowire()
            ->tag(PhilCommerceBundle::ORDER_PROCESSOR_TAG, ['after' => [OptionSupplementProcessor::class, self::PROMOTION_PROCESSOR], 'before' => TaxOrderProcessor::class]);

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineOfferRepository::class, DoctrineSubscriptionRepository::class, DoctrineGiftRepository::class, DoctrineSeatRepository::class] as $adapter) {
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
