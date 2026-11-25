<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use LogicException;
use Phil\Outbox\Contract\Outbox;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\ErasureMessages;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\ExternalPersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\Handler\EraseModuleHandler;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Phil\PrivacyBundle\Infrastructure\Console\ErasureRequestCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\ErasureWorkflowServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\Http\ErasureProgressController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Messenger\ErasureFailureListener;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleCodec;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleRelay;
use Phil\PrivacyBundle\Infrastructure\Outbox\OutboxErasureMessages;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[CoversClass(ErasureWorkflowServices::class)]
#[CoversClass(RightsConfiguration::class)]
#[CoversClass(RightsServices::class)]
#[CoversClass(PrivacyServicesPass::class)]
final class ErasureWorkflowWiringTest extends TestCase
{
    public function testOffByDefaultNoWorkflowRunsAndTheProgressPageAnswersNotFound(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(ErasureRequests::class));
        self::assertFalse($container->hasAlias(ErasureRequestStore::class));
        self::assertFalse($container->hasDefinition(EraseModuleHandler::class));
        self::assertSame('ROLE_PRIVACY_OPERATOR', $container->getParameter(ErasureWorkflowServices::OPERATOR_ROLE));
        self::assertEquals([
            new Reference(PrivacyPages::class),
            new Reference(ErasureRequests::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(CurrentDataSubject::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%'.ErasureWorkflowServices::OPERATOR_ROLE.'%',
        ], $container->getDefinition(ErasureProgressController::class)->getArguments());
        self::assertTrue($container->getDefinition(ErasureProgressController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(ErasureProgressController::class)->isPublic());
    }

    public function testTurnedOnEveryPartIsWired(): void
    {
        $container = $this->load(['erasure_workflow' => ['enabled' => true, 'lease' => 120, 'operator_role' => 'ROLE_DPO']]);

        self::assertSame('ROLE_DPO', $container->getParameter(ErasureWorkflowServices::OPERATOR_ROLE));
        self::assertSame(DbalErasureRequests::class, (string) $container->getAlias(ErasureRequestStore::class));
        self::assertEquals([new Reference('database_connection')], $container->getDefinition(DbalErasureRequests::class)->getArguments());
        self::assertTrue($container->getDefinition(DbalErasureRequests::class)->hasTag(RightsServices::PURGER_TAG));
        self::assertEquals([
            new Reference(ErasureRequestStore::class),
            new Reference(ErasureMessages::class),
            new TaggedIteratorArgument(PhilPrivacyBundle::ERASER_TAG),
            new TaggedIteratorArgument(ErasureWorkflowServices::EXTERNAL_ERASER_TAG),
            new Reference('clock'),
            new Reference(GuestRequestStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(GuestSubjects::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            120,
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(ErasureRequests::class)->getArguments());
        self::assertTrue($container->getDefinition(ErasureRequests::class)->isPublic());
        self::assertSame(OutboxErasureMessages::class, (string) $container->getAlias(ErasureMessages::class));
        self::assertEquals([new Reference(Outbox::class)], $container->getDefinition(OutboxErasureMessages::class)->getArguments());
        self::assertSame([['handles' => EraseModule::class]], $container->getDefinition(EraseModuleHandler::class)->getTag('messenger.message_handler'));
        self::assertSame(['phil_outbox.codec'], array_keys($container->getDefinition(EraseModuleCodec::class)->getTags()));
        self::assertEquals([new Reference(ErasureRequestStore::class)], $container->getDefinition(EraseModuleCodec::class)->getArguments());
        self::assertSame([['event' => EraseModule::class]], $container->getDefinition(EraseModuleRelay::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference('messenger.default_bus')], $container->getDefinition(EraseModuleRelay::class)->getArguments());
        self::assertSame([['event' => WorkerMessageFailedEvent::class]], $container->getDefinition(ErasureFailureListener::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference(ErasureRequests::class)], $container->getDefinition(ErasureFailureListener::class)->getArguments());
        self::assertTrue($container->getDefinition(ErasureRequestCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(ErasureRequests::class)], $container->getDefinition(EraseModuleHandler::class)->getArguments());
        self::assertEquals([new Reference(ErasureRequests::class)], $container->getDefinition(ErasureRequestCommand::class)->getArguments());
        self::assertTrue($container->getAutoconfiguredInstanceof()[ExternalPersonalDataEraser::class]->hasTag(ErasureWorkflowServices::EXTERNAL_ERASER_TAG));
    }

    public function testTheDefaultsAreAFifteenMinuteLease(): void
    {
        self::assertSame(900, $this->load(['erasure_workflow' => ['enabled' => true]])->getDefinition(ErasureRequests::class)->getArgument(7));
    }

    public function testTheShortestAndLongestLeasesAreAccepted(): void
    {
        foreach ([1, 86_400] as $lease) {
            self::assertSame($lease, $this->load(['erasure_workflow' => ['enabled' => true, 'lease' => $lease]])->getDefinition(ErasureRequests::class)->getArgument(7));
        }
    }

    public function testAStoreOfTheApplicationCanBeNamed(): void
    {
        $container = $this->load(['erasure_workflow' => ['enabled' => true, 'store' => 'app.erasures']]);

        self::assertSame('app.erasures', (string) $container->getAlias(ErasureRequestStore::class));
        self::assertFalse($container->hasDefinition(DbalErasureRequests::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedSettings(): iterable
    {
        yield 'no lease' => [['lease' => 0]];
        yield 'a lease over a day' => [['lease' => 86_401]];
        yield 'no operator role' => [['operator_role' => '']];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('refusedSettings')]
    public function testASettingOutOfBoundsStopsTheBuild(array $settings): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['erasure_workflow' => ['enabled' => true] + $settings]);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function missingParts(): iterable
    {
        yield 'outbox' => [['database_connection', 'messenger.default_bus', 'clock'], 'needs phil/outbox-bundle, on the connection of the bundle'];
        yield 'database' => [[Outbox::class, 'messenger.default_bus', 'clock'], 'needs an ErasureRequestStore: Doctrine DBAL for the tables of the bundle, or erasure_workflow.store'];
        yield 'messenger' => [[Outbox::class, 'database_connection', 'clock'], 'needs symfony/messenger'];
        yield 'clock' => [[Outbox::class, 'database_connection', 'messenger.default_bus'], 'needs the clock component (symfony/clock)'];
    }

    /**
     * @param list<string> $present
     */
    #[DataProvider('missingParts')]
    public function testTurnedOnItNeedsTheOutboxMessengerAStoreAndAClock(array $present, string $message): void
    {
        $container = $this->load(['erasure_workflow' => ['enabled' => true]]);

        foreach ($present as $id) {
            $container->register($id, stdClass::class);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.erasure_workflow is enabled and '.$message.'.');

        new PrivacyServicesPass()->process($container);
    }

    public function testWithEveryPartTheDeletionsAndTheGuestRequestsEraseThroughIt(): void
    {
        $container = $this->load(['erasure_workflow' => ['enabled' => true], 'guest_requests' => ['enabled' => true, 'sender' => 'app.sender']]);

        foreach ([Outbox::class, 'database_connection', 'messenger.default_bus', 'clock', 'lock.factory', 'app.sender', 'cache.app'] as $id) {
            $container->register($id, stdClass::class);
        }

        new PrivacyServicesPass()->process($container);

        self::assertEquals(new Reference(ErasureRequests::class), $container->getDefinition(AccountDeletions::class)->getArgument(11));
        self::assertEquals(new Reference(ErasureRequests::class), $container->getDefinition(GuestRequests::class)->getArgument(13));
    }

    public function testWithoutTheDeletionsOrTheGuestRequestsTheWorkflowStaysOnItsOwn(): void
    {
        $container = $this->load(['erasure_workflow' => ['enabled' => true]]);

        foreach ([Outbox::class, 'database_connection', 'messenger.default_bus', 'clock'] as $id) {
            $container->register($id, stdClass::class);
        }

        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(ErasureRequests::class));
        self::assertFalse($container->hasDefinition(AccountDeletions::class));
        self::assertFalse($container->hasDefinition(GuestRequests::class));
    }

    public function testWithoutADatabaseTheTableStoreGoesAwayAndAStoreOfTheApplicationStays(): void
    {
        $default = $this->load(['erasure_workflow' => ['enabled' => true]]);
        $default->register(Outbox::class, stdClass::class);

        try {
            new PrivacyServicesPass()->process($default);
            self::fail('A workflow without a store was built.');
        } catch (LogicException) {
        }

        self::assertFalse($default->hasDefinition(DbalErasureRequests::class));
        self::assertFalse($default->hasAlias(ErasureRequestStore::class));

        $own = $this->load(['erasure_workflow' => ['enabled' => true, 'store' => 'app.erasures']]);

        foreach ([Outbox::class, 'app.erasures', 'messenger.default_bus', 'clock'] as $id) {
            $own->register($id, stdClass::class);
        }

        new PrivacyServicesPass()->process($own);

        self::assertSame('app.erasures', (string) $own->getAlias(ErasureRequestStore::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $bundle = new PhilPrivacyBundle('php://memory');
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => RightsServices::SECURITY_BUNDLE]);
        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [$config]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
