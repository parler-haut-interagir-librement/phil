<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit;

use Phil\ReservationBundle\Application\Contract\ReminderSender;
use Phil\ReservationBundle\Application\Reminders;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Infrastructure\Calendar\ReservationItems;
use Phil\ReservationBundle\Infrastructure\Console\ExpireHoldsCommand;
use Phil\ReservationBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineReservationRepository;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineResourceRepository;
use Phil\ReservationBundle\Infrastructure\Form\ReservationType;
use Phil\ReservationBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use Phil\ReservationBundle\PhilReservationBundle;
use Phil\ReservationBundle\Tests\Unit\Fixture\RecordingSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PhilReservationBundle::class)]
final class PhilReservationBundleTest extends TestCase
{
    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([['batch' => 40]]);

        self::assertSame('phil_reservation', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineResourceRepository::class, DoctrineReservationRepository::class, Reservations::class, ViolationMessages::class, ReservationType::class, MessengerReminderSender::class, Reminders::class, ExpireHoldsCommand::class, SendRemindersCommand::class, ReservationItems::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineResourceRepository::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineReservationRepository::class)->getArguments());
        self::assertSame(DoctrineResourceRepository::class, (string) $container->getAlias(ResourceRepository::class));
        self::assertTrue($container->getAlias(ResourceRepository::class)->isPublic());
        self::assertSame(DoctrineReservationRepository::class, (string) $container->getAlias(ReservationRepository::class));
        self::assertTrue($container->getAlias(ReservationRepository::class)->isPublic());
        self::assertEquals([
            new Reference(DoctrineResourceRepository::class),
            new Reference(DoctrineReservationRepository::class),
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(Reservations::class)->getArguments());
        self::assertTrue($container->getDefinition(Reservations::class)->isPublic());
        self::assertEquals([new Reference('translator')], $container->getDefinition(ViolationMessages::class)->getArguments());
        self::assertTrue($container->getDefinition(ViolationMessages::class)->isPublic());
        self::assertEquals([new Reference(Reservations::class), new Reference(ViolationMessages::class), new Reference('translator')], $container->getDefinition(ReservationType::class)->getArguments());
        self::assertTrue($container->getDefinition(ReservationType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(MessageBusInterface::class)], $container->getDefinition(MessengerReminderSender::class)->getArguments());
        self::assertSame(MessengerReminderSender::class, (string) $container->getAlias(ReminderSender::class));
        self::assertEquals([new Reference(DoctrineResourceRepository::class), new Reference(DoctrineReservationRepository::class), new Reference(ReminderSender::class), new Reference('clock')], $container->getDefinition(Reminders::class)->getArguments());
        self::assertTrue($container->getDefinition(Reminders::class)->isPublic());
        self::assertEquals([new Reference(Reservations::class), 40], $container->getDefinition(ExpireHoldsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(ExpireHoldsCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(Reminders::class), 40], $container->getDefinition(SendRemindersCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(SendRemindersCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(DoctrineReservationRepository::class), 40], $container->getDefinition(ReservationItems::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ReservationItems::class)->getTag('phil_calendar.item_source'));
        self::assertSame(500, $this->load([])->getDefinition(ExpireHoldsCommand::class)->getArgument(1));
    }

    public function testTheCalendarIsLeftOutWhenItIsTurnedOff(): void
    {
        self::assertFalse($this->load([['calendar' => false]])->hasDefinition(ReservationItems::class));
        self::assertTrue($this->load([['calendar' => true]])->hasDefinition(ReservationItems::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'no batch' => [['batch' => 0]];
        yield 'too large a batch' => [['batch' => 10_001]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testTheConfigurationIsBounded(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheBatchBoundsAreAccepted(): void
    {
        self::assertSame(1, $this->load([['batch' => 1]])->getDefinition(ExpireHoldsCommand::class)->getArgument(1));
        self::assertSame(10_000, $this->load([['batch' => 10_000]])->getDefinition(ExpireHoldsCommand::class)->getArgument(1));
    }

    public function testRemindersGoThroughTheBusWhenThereIsOne(): void
    {
        $container = $this->load([]);
        $container->register(MessageBusInterface::class, MessageBusInterface::class);
        $this->builtPass($container)->process($container);

        self::assertSame([true, true, true, true], [$container->hasDefinition(MessengerReminderSender::class), $container->hasAlias(ReminderSender::class), $container->hasDefinition(Reminders::class), $container->hasDefinition(SendRemindersCommand::class)]);
    }

    public function testWithoutABusNorASenderThereIsNoReminder(): void
    {
        $container = $this->load([]);
        $this->builtPass($container)->process($container);

        self::assertSame([false, false, false, false], [$container->hasDefinition(MessengerReminderSender::class), $container->hasAlias(ReminderSender::class), $container->hasDefinition(Reminders::class), $container->hasDefinition(SendRemindersCommand::class)]);
        self::assertTrue($container->hasDefinition(ExpireHoldsCommand::class));
    }

    public function testTheApplicationsOwnSenderIsKeptWithoutABus(): void
    {
        $container = $this->load([]);
        $container->register(RecordingSender::class, RecordingSender::class);
        $container->setAlias(ReminderSender::class, RecordingSender::class);
        $this->builtPass($container)->process($container);

        self::assertSame([false, true, true, true], [$container->hasDefinition(MessengerReminderSender::class), $container->hasAlias(ReminderSender::class), $container->hasDefinition(Reminders::class), $container->hasDefinition(SendRemindersCommand::class)]);
        self::assertSame(RecordingSender::class, (string) $container->getAlias(ReminderSender::class));

        $defined = $this->load([]);
        $defined->removeAlias(ReminderSender::class);
        $defined->register(ReminderSender::class, RecordingSender::class);
        $this->builtPass($defined)->process($defined);
        self::assertTrue($defined->hasDefinition(Reminders::class));
    }

    public function testTheEntitiesAreMapped(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $container->registerExtension($extension);

        $extension->prepend($container);

        /** @var array{orm?: array{mappings?: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        $mapping = $doctrine['orm']['mappings']['PhilReservationBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertSame(['attribute', false, 'Phil\ReservationBundle\Domain\Model'], [$mapping['type'] ?? null, $mapping['is_bundle'] ?? null, $mapping['prefix'] ?? null]);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);
        self::assertDirectoryExists($mapping['dir']);

        $withoutDoctrine = new ContainerBuilder();
        $withoutDoctrine->registerExtension($extension);

        $extension->prepend($withoutDoctrine);
        self::assertSame([], $withoutDoctrine->getExtensionConfig('doctrine'));
    }

    private function builtPass(ContainerBuilder $container): CompilerPassInterface
    {
        $before = $container->getCompilerPassConfig()->getPasses();
        new PhilReservationBundle()->build($container);
        $added = array_values(array_filter($container->getCompilerPassConfig()->getPasses(), static fn (CompilerPassInterface $pass): bool => !\in_array($pass, $before, true)));
        self::assertCount(1, $added);

        return $added[0];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilReservationBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
