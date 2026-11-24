<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\ConfiguredPhoneRequirement;
use Phil\CommerceSubscriptionBundle\Application\Contract\DeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Application\Contract\PhoneRequirement;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\Gifts;
use Phil\CommerceSubscriptionBundle\Application\Handler\PurgeDeliveryAddressesHandler;
use Phil\CommerceSubscriptionBundle\Application\Message\PurgeDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\ProrationOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Seats;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Console\PurgeDeliveryAddressesCommand;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineDeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineGiftRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineOfferRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSeatRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine\DoctrineSubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Infrastructure\Privacy\DeliveryAddressPrivacy;
use Phil\CommerceSubscriptionBundle\Infrastructure\Scheduler\DeliveryAddressSchedule;
use Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
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

        foreach ([OfferRepository::class => DoctrineOfferRepository::class, SubscriptionRepository::class => DoctrineSubscriptionRepository::class, GiftRepository::class => DoctrineGiftRepository::class, SeatRepository::class => DoctrineSeatRepository::class, DeliveryAddressRepository::class => DoctrineDeliveryAddressRepository::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
            $argument = $container->getDefinition($adapter)->getArgument(0);
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame('doctrine.orm.entity_manager', (string) $argument);
        }
    }

    /**
     * Addresses kept 90 days after their last shipment, purged by batches of 500 every day, by a command and a
     * schedule under the default lock factory when one exists.
     */
    public function testItWiresTheDeliveryAddresses(): void
    {
        $container = $this->load([]);

        $addresses = $container->getDefinition(DeliveryAddresses::class);
        self::assertTrue($addresses->isPublic());
        self::assertTrue($addresses->isAutowired());
        self::assertSame(90, $addresses->getArgument('$retentionDays'));
        $wired = $container->getDefinition(Subscriptions::class)->getArgument('$addresses');
        self::assertInstanceOf(Reference::class, $wired);
        self::assertSame(DeliveryAddresses::class, (string) $wired);

        self::assertSame([[]], $container->getDefinition(ConfiguredPhoneRequirement::class)->getArguments());
        self::assertSame(ConfiguredPhoneRequirement::class, (string) $container->getAlias(PhoneRequirement::class));

        $handler = $container->getDefinition(PurgeDeliveryAddressesHandler::class);
        self::assertTrue($handler->isAutowired());
        self::assertSame(500, $handler->getArgument('$limit'));
        self::assertSame([['handles' => PurgeDeliveryAddresses::class]], $handler->getTag('messenger.message_handler'));
        $this->assertOptional('clock', $handler->getArgument('$clock'));

        $command = $container->getDefinition(PurgeDeliveryAddressesCommand::class);
        self::assertTrue($command->isAutowired());
        self::assertSame([[]], $command->getTag('console.command'));
        $this->assertOptional('lock.factory', $command->getArgument('$locks'));
        $this->assertOptional('clock', $command->getArgument('$clock'));

        $schedule = $container->getDefinition(DeliveryAddressSchedule::class);
        self::assertSame([['name' => DeliveryAddressSchedule::NAME]], $schedule->getTag('scheduler.schedule_provider'));
        self::assertSame('1 day', $schedule->getArgument(0));
        $this->assertOptional('lock.factory', $schedule->getArgument(1));

        self::assertFalse($container->hasDefinition(DeliveryAddressPrivacy::class));
    }

    public function testTheDeliveryAddressesAreConfigured(): void
    {
        $container = $this->load(['delivery_addresses' => [
            'retention_days' => 30,
            'phone_required_methods' => ['courier', 'express', 'courier'],
            'purge_batch' => 50,
            'schedule' => ['frequency' => '6 hours'],
            'lock_factory' => 'my.locks',
        ]]);

        self::assertSame(30, $container->getDefinition(DeliveryAddresses::class)->getArgument('$retentionDays'));
        self::assertSame([['courier', 'express']], $container->getDefinition(ConfiguredPhoneRequirement::class)->getArguments());
        self::assertSame(50, $container->getDefinition(PurgeDeliveryAddressesHandler::class)->getArgument('$limit'));
        self::assertSame('6 hours', $container->getDefinition(DeliveryAddressSchedule::class)->getArgument(0));
        $this->assertOptional('my.locks', $container->getDefinition(DeliveryAddressSchedule::class)->getArgument(1));
        $this->assertOptional('my.locks', $container->getDefinition(PurgeDeliveryAddressesCommand::class)->getArgument('$locks'));

        $without = $this->load(['delivery_addresses' => ['lock_factory' => null, 'schedule' => false]]);
        self::assertNull($without->getDefinition(PurgeDeliveryAddressesCommand::class)->getArgument('$locks'));
        self::assertFalse($without->hasDefinition(DeliveryAddressSchedule::class));

        foreach ([['retention_days' => 0], ['retention_days' => 3_651], ['purge_batch' => 0], ['purge_batch' => 10_001], ['phone_required_methods' => ['']]] as $wrong) {
            try {
                $this->load(['delivery_addresses' => $wrong]);
                self::fail('The delivery addresses were accepted: '.json_encode($wrong));
            } catch (InvalidConfigurationException) {
            }
        }

        self::assertSame(1, $this->load(['delivery_addresses' => ['retention_days' => 1]])->getDefinition(DeliveryAddresses::class)->getArgument('$retentionDays'));
        self::assertSame(3_650, $this->load(['delivery_addresses' => ['retention_days' => 3_650]])->getDefinition(DeliveryAddresses::class)->getArgument('$retentionDays'));
        self::assertSame(10_000, $this->load(['delivery_addresses' => ['purge_batch' => 10_000]])->getDefinition(PurgeDeliveryAddressesHandler::class)->getArgument('$limit'));
    }

    public function testWithThePrivacyBundleTheAddressesAreExportedAndErased(): void
    {
        $privacy = $this->load([], ['DoctrineBundle' => DoctrineBundle::class, 'PhilPrivacyBundle' => PhilPrivacyBundle::class])->getDefinition(DeliveryAddressPrivacy::class);

        self::assertTrue($privacy->isAutowired());
        self::assertSame([[]], $privacy->getTag('phil_privacy.exporter'));
        self::assertSame([[]], $privacy->getTag('phil_privacy.eraser'));
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

    private function assertOptional(string $id, mixed $argument): void
    {
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame($id, (string) $argument);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $argument->getInvalidBehavior());
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
