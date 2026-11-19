<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Phil\CommerceSyndicationBundle\Application\Forwarder;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Application\Ledger;
use Phil\CommerceSyndicationBundle\Application\MessageRetention;
use Phil\CommerceSyndicationBundle\Application\PartnerReturns;
use Phil\CommerceSyndicationBundle\Application\Partners;
use Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler;
use Phil\CommerceSyndicationBundle\Application\Port\MessageTransport;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Application\ShipmentTracking;
use Phil\CommerceSyndicationBundle\Application\SyndicatedSellables;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\DeliverMessagesCommand;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\PurgeMessagesCommand;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\FedSkuIndex;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\HttpClientTransport;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController;
use Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommerceSyndicationBundle::class)]
final class PhilCommerceSyndicationBundleTest extends TestCase
{
    private const array CONFIG = ['identity' => 'shop', 'signing_key' => 'whsk_secret'];

    public function testItWiresTheSyndication(): void
    {
        $container = $this->load(self::CONFIG);

        self::assertSame('phil_commerce_syndication', $this->extension()->getAlias());

        foreach ([Partners::class, Publisher::class, Forwarder::class, Ledger::class, Inbox::class, Courier::class, SyndicationWebhookController::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        $sellables = $container->getDefinition(SyndicatedSellables::class);
        self::assertTrue($sellables->isAutowired());
        self::assertSame([[]], $sellables->getTag('phil_commerce.sellable_resolver'));
        self::assertSame([[]], $sellables->getTag('phil_commerce.sellable_availability'));
        self::assertSame([[]], $container->getDefinition(SyndicationWebhookController::class)->getTag('controller.service_arguments'));
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(FedSkuIndex::class)->getTag('doctrine.event_listener'));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['identity' => 'shop', 'signing_key' => 'whsk_secret', 'tolerance' => 60, 'max_attempts' => 3, 'retention_days' => 30, 'cost_grace_days' => 2]);

        $inbox = $container->getDefinition(Inbox::class);
        self::assertSame(['shop', 60, 2], [$inbox->getArgument('$identity'), $inbox->getArgument('$tolerance'), $inbox->getArgument('$costGraceDays')]);
        self::assertSame(30, $container->getDefinition(MessageRetention::class)->getArgument('$retentionDays'));
        $handlers = $inbox->getArgument('$handlers');
        self::assertInstanceOf(TaggedIteratorArgument::class, $handlers);
        self::assertSame('phil_commerce_syndication.forwarded_order_handler', $handlers->getTag());
        $courier = $container->getDefinition(Courier::class);
        self::assertSame(['whsk_secret', 3], [$courier->getArgument('$signingKey'), $courier->getArgument('$maxAttempts')]);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load(self::CONFIG);

        self::assertSame(300, $container->getDefinition(Inbox::class)->getArgument('$tolerance'));
        self::assertSame(8, $container->getDefinition(Courier::class)->getArgument('$maxAttempts'));
        self::assertSame(7, $container->getDefinition(Inbox::class)->getArgument('$costGraceDays'));
        self::assertSame(90, $container->getDefinition(MessageRetention::class)->getArgument('$retentionDays'));
    }

    public function testTheBoundsAreAccepted(): void
    {
        $container = $this->load(['tolerance' => 1, 'max_attempts' => 20] + self::CONFIG);
        self::assertSame(1, $container->getDefinition(Inbox::class)->getArgument('$tolerance'));
        self::assertSame(20, $container->getDefinition(Courier::class)->getArgument('$maxAttempts'));

        self::assertSame(1, $this->load(['max_attempts' => 1] + self::CONFIG)->getDefinition(Courier::class)->getArgument('$maxAttempts'));
        self::assertSame(1, $this->load(['retention_days' => 1] + self::CONFIG)->getDefinition(MessageRetention::class)->getArgument('$retentionDays'));
        self::assertSame(0, $this->load(['cost_grace_days' => 0] + self::CONFIG)->getDefinition(Inbox::class)->getArgument('$costGraceDays'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'no identity' => [['signing_key' => 'whsk_secret']];
        yield 'an empty identity' => [['identity' => ''] + self::CONFIG];
        yield 'no key' => [['identity' => 'shop']];
        yield 'an empty key' => [['signing_key' => ''] + self::CONFIG];
        yield 'no tolerance' => [['tolerance' => 0] + self::CONFIG];
        yield 'no attempt' => [['max_attempts' => 0] + self::CONFIG];
        yield 'too many attempts' => [['max_attempts' => 21] + self::CONFIG];
        yield 'no retention' => [['retention_days' => 0] + self::CONFIG];
        yield 'a negative grace' => [['cost_grace_days' => -1] + self::CONFIG];
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

    public function testTheHandlersAreTaggedByAutoconfiguration(): void
    {
        $container = $this->load(self::CONFIG, []);

        self::assertSame(['phil_commerce_syndication.forwarded_order_handler' => [[]]], $container->getAutoconfiguredInstanceof()[ForwardedOrderHandler::class]->getTags());
        self::assertSame(['phil_commerce_syndication.shipment_handler' => [[]]], $container->getAutoconfiguredInstanceof()[PartnerShipmentHandler::class]->getTags());
        self::assertSame(['phil_commerce_syndication.return_handler' => [[]]], $container->getAutoconfiguredInstanceof()[PartnerReturnHandler::class]->getTags());
    }

    public function testTheParcelsAndReturnsAreWired(): void
    {
        $container = $this->load(self::CONFIG);

        foreach ([ShipmentTracking::class => 'phil_commerce_syndication.shipment_handler', PartnerReturns::class => 'phil_commerce_syndication.return_handler'] as $service => $tag) {
            $definition = $container->getDefinition($service);
            self::assertTrue($definition->isPublic() && $definition->isAutowired(), $service);
            $handlers = $definition->getArgument('$handlers');
            self::assertInstanceOf(TaggedIteratorArgument::class, $handlers);
            self::assertSame($tag, $handlers->getTag());
        }
    }

    public function testTheTransportAndTheCommandAreRegistered(): void
    {
        $container = $this->load(self::CONFIG);

        self::assertTrue($container->getDefinition(HttpClientTransport::class)->isAutowired());
        self::assertSame(HttpClientTransport::class, (string) $container->getAlias(MessageTransport::class));
        foreach ([DeliverMessagesCommand::class, PurgeMessagesCommand::class] as $class) {
            $command = $container->getDefinition($class);
            self::assertTrue($command->isAutowired());
            self::assertSame([[]], $command->getTag('console.command'));
        }

        self::assertTrue($container->getDefinition(MessageRetention::class)->isPublic());
    }

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load(self::CONFIG);

        foreach ([PartnerRepository::class, MessageRepository::class, SyndicatedProductRepository::class, LedgerRepository::class, PublishedPriceRepository::class] as $port) {
            self::assertSame(DoctrineSyndicationRepository::class, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
        }

        $argument = $container->getDefinition(DoctrineSyndicationRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommerceSyndicationBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommerceSyndicationBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommerceSyndicationBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load(self::CONFIG, []);

        foreach ([SyndicatedSellables::class, Inbox::class, Courier::class, MessageRetention::class, HttpClientTransport::class, DeliverMessagesCommand::class, PurgeMessagesCommand::class, DoctrineSyndicationRepository::class, FedSkuIndex::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }

        self::assertFalse($container->hasAlias(PartnerRepository::class));
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
        $this->expectExceptionMessageIsOrContains('PhilCommerceSyndicationBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

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
        $extension = new PhilCommerceSyndicationBundle()->getContainerExtension();
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
