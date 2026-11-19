<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\ProrationOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Seats;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineGiftRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle;
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

#[CoversClass(PhilCommerceSubscriptionBundle::class)]
final class PhilCommerceSubscriptionBundleTest extends TestCase
{
    public function testItWiresTheSubscriptionServices(): void
    {
        $container = $this->load(['payment_attempts' => 5]);

        self::assertSame('phil_commerce_subscription', $this->extension()->getAlias());
        self::assertTrue($container->getDefinition(OfferSellables::class)->isAutoconfigured());
        self::assertTrue($container->getDefinition(OfferSellables::class)->isAutowired());
        self::assertFalse($container->getDefinition(OfferSellables::class)->isPublic());
        self::assertTrue($container->getDefinition(Subscriptions::class)->isPublic());
        self::assertTrue($container->getDefinition(Subscriptions::class)->isAutowired());
        self::assertSame(5, $container->getDefinition(Subscriptions::class)->getArgument('$paymentAttempts'));
        self::assertTrue($container->getDefinition(FeatureAccess::class)->isPublic());
        self::assertTrue($container->getDefinition(FeatureAccess::class)->isAutowired());
        self::assertSame([['method' => 'reset']], $container->getDefinition(FeatureAccess::class)->getTag('kernel.reset'));
        $access = $container->getDefinition(Subscriptions::class)->getArgument('$access');
        self::assertInstanceOf(Reference::class, $access);
        self::assertSame(FeatureAccess::class, (string) $access);
    }

    public function testPendingSubscriptionsAreAbandonedAfterSevenDaysByDefault(): void
    {
        self::assertSame(7, $this->load([])->getDefinition(Subscriptions::class)->getArgument('$abandonPendingAfterDays'));
        self::assertSame(30, $this->load(['abandon_pending_after_days' => 30])->getDefinition(Subscriptions::class)->getArgument('$abandonPendingAfterDays'));
        self::assertSame(1, $this->load(['abandon_pending_after_days' => 1])->getDefinition(Subscriptions::class)->getArgument('$abandonPendingAfterDays'));
        self::assertSame(365, $this->load(['abandon_pending_after_days' => 365])->getDefinition(Subscriptions::class)->getArgument('$abandonPendingAfterDays'));

        foreach ([0, 366] as $days) {
            try {
                $this->load(['abandon_pending_after_days' => $days]);
                self::fail('The days were accepted.');
            } catch (InvalidConfigurationException) {
            }
        }
    }

    /**
     * Downgrades wait for the renewal unless set, gifts redeem for a year unless set; the proration runs after the
     * options and the promotions, before the taxes.
     */
    public function testItWiresChangesGiftsAndSeats(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->getDefinition(Subscriptions::class)->getArgument('$immediateDowngrades'));
        self::assertTrue($this->load(['immediate_downgrades' => true])->getDefinition(Subscriptions::class)->getArgument('$immediateDowngrades'));
        self::assertSame(365, $container->getDefinition(Gifts::class)->getArgument('$redeemWithinDays'));
        self::assertSame(1, $this->load(['gift_redeem_within_days' => 1])->getDefinition(Gifts::class)->getArgument('$redeemWithinDays'));
        self::assertSame(3_650, $this->load(['gift_redeem_within_days' => 3_650])->getDefinition(Gifts::class)->getArgument('$redeemWithinDays'));

        foreach ([Gifts::class, Seats::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic());
            self::assertTrue($container->getDefinition($service)->isAutowired());
            $access = $container->getDefinition($service)->getArgument('$access');
            self::assertInstanceOf(Reference::class, $access);
            self::assertSame(FeatureAccess::class, (string) $access);
        }

        $organizations = $container->getDefinition(FeatureAccess::class)->getArgument('$organizations');
        self::assertInstanceOf(Reference::class, $organizations);
        self::assertSame(OrganizationMembership::class, (string) $organizations);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $organizations->getInvalidBehavior());

        $processor = $container->getDefinition(ProrationOrderProcessor::class);
        self::assertTrue($processor->isAutowired());
        self::assertFalse($processor->isAutoconfigured());
        self::assertSame([['after' => [OptionSupplementProcessor::class, 'Phil\\CommerceCatalogBundle\\Application\\PromotionProcessor'], 'before' => TaxOrderProcessor::class]], $processor->getTag('phil_commerce.order_processor'));

        foreach ([0, 3_651] as $days) {
            try {
                $this->load(['gift_redeem_within_days' => $days]);
                self::fail('The days were accepted.');
            } catch (InvalidConfigurationException) {
            }
        }
    }

    public function testThreeAttemptsByDefaultAndOneToTenAccepted(): void
    {
        self::assertSame(3, $this->load([])->getDefinition(Subscriptions::class)->getArgument('$paymentAttempts'));
        self::assertSame(1, $this->load(['payment_attempts' => 1])->getDefinition(Subscriptions::class)->getArgument('$paymentAttempts'));
        self::assertSame(10, $this->load(['payment_attempts' => 10])->getDefinition(Subscriptions::class)->getArgument('$paymentAttempts'));
    }

    public function testAttemptsOutsideTheirBoundsAreRefused(): void
    {
        foreach ([0, 11] as $attempts) {
            try {
                $this->load(['payment_attempts' => $attempts]);
                self::fail('The attempts were accepted.');
            } catch (InvalidConfigurationException) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function testWithDoctrineOffersAndSubscriptionsAreStored(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class]);

        foreach ([OfferRepository::class => DoctrineOfferRepository::class, SubscriptionRepository::class => DoctrineSubscriptionRepository::class, GiftRepository::class => DoctrineGiftRepository::class, SeatRepository::class => DoctrineSeatRepository::class] as $port => $adapter) {
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

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceSubscriptionBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceSubscriptionBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceSubscriptionBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(Subscriptions::class));
        self::assertFalse($container->hasAlias(OfferRepository::class));
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceSubscriptionBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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
        $extension = new PhilCommerceSubscriptionBundle()->getContainerExtension();
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
