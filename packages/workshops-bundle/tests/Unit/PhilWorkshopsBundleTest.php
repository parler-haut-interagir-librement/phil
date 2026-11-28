<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit;

use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Event\EntryLeft;
use Phil\WorkshopsBundle\Application\Contract\SeatClaims;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Contract\SessionRepository;
use Phil\WorkshopsBundle\Domain\Contract\WorkshopRepository;
use Phil\WorkshopsBundle\Domain\Event\RegistrationCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionResized;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineRegistrationRepository;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineSessionRepository;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineWorkshopRepository;
use Phil\WorkshopsBundle\Infrastructure\Translation\RefusalMessages;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlistListener;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;
use Phil\WorkshopsBundle\Integration\Waitlist\WaitlistSeatClaims;
use Phil\WorkshopsBundle\PhilWorkshopsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilWorkshopsBundle::class)]
final class PhilWorkshopsBundleTest extends TestCase
{
    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([['max_seats' => 4, 'waitlist' => false]]);

        self::assertSame('phil_workshops', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineWorkshopRepository::class, DoctrineSessionRepository::class, DoctrineRegistrationRepository::class, Workshops::class, RefusalMessages::class], array_keys($container->getDefinitions()));

        foreach ([WorkshopRepository::class => DoctrineWorkshopRepository::class, SessionRepository::class => DoctrineSessionRepository::class, RegistrationRepository::class => DoctrineRegistrationRepository::class] as $port => $adapter) {
            self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition($adapter)->getArguments());
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
        }

        self::assertFalse($container->hasAlias(SeatClaims::class));
        self::assertEquals([
            new Reference(WorkshopRepository::class),
            new Reference(SessionRepository::class),
            new Reference(RegistrationRepository::class),
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            4,
            new Reference(SeatClaims::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(Workshops::class)->getArguments());
        self::assertTrue($container->getDefinition(Workshops::class)->isPublic());
        self::assertEquals([new Reference('translator')], $container->getDefinition(RefusalMessages::class)->getArguments());
        self::assertTrue($container->getDefinition(RefusalMessages::class)->isPublic());
    }

    public function testTheDefaults(): void
    {
        self::assertSame(10, $this->load([])->getDefinition(Workshops::class)->getArgument(5));
    }

    public function testEachSessionHasAWaitingListWhenTheWaitlistBundleIsInstalled(): void
    {
        $container = $this->load([['max_seats' => 3]]);

        self::assertSame(WaitlistSeatClaims::class, (string) $container->getAlias(SeatClaims::class));
        self::assertFalse($container->getAlias(SeatClaims::class)->isPublic());
        self::assertEquals([new Reference(Waitlists::class)], $container->getDefinition(WaitlistSeatClaims::class)->getArguments());
        self::assertEquals([
            new Reference(SessionRepository::class),
            new Reference(RegistrationRepository::class),
            new Reference(Waitlists::class),
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            3,
        ], $container->getDefinition(SessionWaitlists::class)->getArguments());
        self::assertTrue($container->getDefinition(SessionWaitlists::class)->isPublic());

        $listener = $container->getDefinition(SessionWaitlistListener::class);
        self::assertEquals([new Reference(SessionWaitlists::class)], $listener->getArguments());
        self::assertSame([['event' => RegistrationCancelled::class], ['event' => SessionResized::class], ['event' => SessionCancelled::class], ['event' => EntryLeft::class]], $listener->getTag('kernel.event_listener'));
        self::assertSame([RegistrationCancelled::class, SessionResized::class, SessionCancelled::class, EntryLeft::class], PhilWorkshopsBundle::WAITLIST_EVENTS);
    }

    public function testTheIntegrationLeavesWhenTheWaitlistBundleIsNotEnabled(): void
    {
        $bundle = new PhilWorkshopsBundle();
        $container = $this->load([[]]);
        $bundle->process($container);

        foreach ([WaitlistSeatClaims::class, SessionWaitlists::class, SessionWaitlistListener::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }

        self::assertFalse($container->hasAlias(SeatClaims::class));
        self::assertTrue($container->hasDefinition(Workshops::class));

        $enabled = $this->load([[]]);
        $enabled->register(Waitlists::class);

        $bundle->process($enabled);

        foreach ([WaitlistSeatClaims::class, SessionWaitlists::class, SessionWaitlistListener::class] as $id) {
            self::assertTrue($enabled->hasDefinition($id), $id);
        }

        self::assertTrue($enabled->hasAlias(SeatClaims::class));

        // Without the integration, there is nothing to remove.
        $off = $this->load([['waitlist' => false]]);
        $bundle->process($off);
        self::assertTrue($off->hasDefinition(Workshops::class));
    }

    public function testTheBundleAddsItselfAsACompilerPass(): void
    {
        $bundle = new PhilWorkshopsBundle();
        $container = new ContainerBuilder();
        $bundle->build($container);

        self::assertContains($bundle, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badOptions(): iterable
    {
        yield 'no seat' => [['max_seats' => 0]];
        yield 'too many seats' => [['max_seats' => 1001]];
        yield 'a removed option' => [['batch' => 100]];
        yield 'a waiting list that is not a boolean' => [['waitlist' => 'yes']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badOptions')]
    public function testAnOptionOutOfBoundsIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheBoundsAreTaken(): void
    {
        self::assertSame(1, $this->load([['max_seats' => 1]])->getDefinition(Workshops::class)->getArgument(5));
        self::assertSame(1000, $this->load([['max_seats' => 1000]])->getDefinition(Workshops::class)->getArgument(5));
    }

    public function testTheEntitiesAreMappedWhenDoctrineIsThere(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('doctrine'));

        $with = new ContainerBuilder();
        $with->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension->prepend($with);

        self::assertSame([['orm' => ['mappings' => ['PhilWorkshopsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\WorkshopsBundle\Domain\Model',
        ]]]]], $with->getExtensionConfig('doctrine'));
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
        $extension = new PhilWorkshopsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
