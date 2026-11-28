<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit;

use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\WaitlistBundle\Application\Contract\OfferPolicy;
use Phil\WaitlistBundle\Application\Policy\FirstInLine;
use Phil\WaitlistBundle\Application\Policy\FirstThatFits;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Contract\WaitlistRepository;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Infrastructure\Console\LapseOffersCommand;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineEntryRepository;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineWaitlistRepository;
use Phil\WaitlistBundle\Integration\Notifications\WaitlistNotifier;
use Phil\WaitlistBundle\PhilWaitlistBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilWaitlistBundle::class)]
final class PhilWaitlistBundleTest extends TestCase
{
    public function testTheDefaultsWireTheServiceOnTheDoctrineAdapters(): void
    {
        $container = $this->load([]);
        $arguments = $container->getDefinition(Waitlists::class)->getArguments();

        self::assertSame(
            [WaitlistRepository::class, EntryRepository::class, OfferPolicy::class, 'clock', 'event_dispatcher'],
            array_map(static fn (mixed $argument): string => $argument instanceof Reference ? (string) $argument : '', \array_slice($arguments, 0, 5)),
        );
        self::assertSame([172_800, 10, 100], \array_slice($arguments, 5));
        self::assertTrue($container->getDefinition(Waitlists::class)->isPublic());
        self::assertSame(DoctrineWaitlistRepository::class, (string) $container->getAlias(WaitlistRepository::class));
        self::assertSame(DoctrineEntryRepository::class, (string) $container->getAlias(EntryRepository::class));
        self::assertTrue($container->getAlias(EntryRepository::class)->isPublic());
        self::assertSame(FirstInLine::class, (string) $container->getAlias(OfferPolicy::class));
        self::assertSame('doctrine.orm.entity_manager', (string) $this->reference($container->getDefinition(DoctrineEntryRepository::class), 0));
        self::assertTrue($container->getDefinition(LapseOffersCommand::class)->hasTag('console.command'));
        self::assertSame(Waitlists::class, (string) $this->reference($container->getDefinition(LapseOffersCommand::class), 0));
        self::assertTrue($container->hasDefinition(FirstInLine::class));
        self::assertTrue($container->hasDefinition(FirstThatFits::class));
    }

    public function testTheOptionsReachTheService(): void
    {
        $container = $this->load([['offer_window' => 600, 'max_places' => 2, 'policy' => 'first_that_fits', 'batch' => 7]]);

        self::assertSame([600, 2, 7], \array_slice($container->getDefinition(Waitlists::class)->getArguments(), 5));
        self::assertSame(FirstThatFits::class, (string) $container->getAlias(OfferPolicy::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'window too short' => [['offer_window' => 59]];
        yield 'window too long' => [['offer_window' => 2_592_001]];
        yield 'no place' => [['max_places' => 0]];
        yield 'too many places' => [['max_places' => 1_001]];
        yield 'unknown policy' => [['policy' => 'random']];
        yield 'empty batch' => [['batch' => 0]];
        yield 'batch too large' => [['batch' => 10_001]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAConfigurationOutOfBoundsIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $container = $this->load([['offer_window' => 60, 'max_places' => 1_000, 'batch' => 10_000]]);
        self::assertSame([60, 1_000, 10_000], \array_slice($container->getDefinition(Waitlists::class)->getArguments(), 5));

        $container = $this->load([['offer_window' => 2_592_000, 'max_places' => 1, 'batch' => 1]]);
        self::assertSame([2_592_000, 1, 1], \array_slice($container->getDefinition(Waitlists::class)->getArguments(), 5));
    }

    public function testTheNotifierListensToTheEventsWithAType(): void
    {
        $container = $this->load([]);
        $notifier = $container->getDefinition(WaitlistNotifier::class);

        self::assertSame([Notifications::class, NotificationTypes::class, EntryRepository::class], [(string) $this->reference($notifier, 0), (string) $this->reference($notifier, 1), (string) $this->reference($notifier, 2)]);
        self::assertSame([PlaceOffered::class => 'waitlist.offered', OfferLapsed::class => 'waitlist.lapsed'], $notifier->getArgument(3));
        self::assertSame([['event' => PlaceOffered::class], ['event' => OfferLapsed::class]], $notifier->getTag('kernel.event_listener'));

        $all = $this->load([['notifications' => ['joined' => 'waitlist.joined', 'accepted' => 'waitlist.accepted']]])->getDefinition(WaitlistNotifier::class);
        self::assertSame([EntryJoined::class => 'waitlist.joined', PlaceOffered::class => 'waitlist.offered', OfferAccepted::class => 'waitlist.accepted', OfferLapsed::class => 'waitlist.lapsed'], $all->getArgument(3));
    }

    public function testWithoutATypeThereIsNoNotifier(): void
    {
        self::assertFalse($this->load([['notifications' => ['offered' => null, 'lapsed' => null]]])->hasDefinition(WaitlistNotifier::class));
    }

    public function testTheNotifierIsRemovedWithoutTheNotificationsBundle(): void
    {
        $container = $this->load([]);
        $bundle = new PhilWaitlistBundle();
        $bundle->process($container);
        self::assertFalse($container->hasDefinition(WaitlistNotifier::class));

        $container = $this->load([]);
        $container->register(Notifications::class);

        $bundle->process($container);
        self::assertTrue($container->hasDefinition(WaitlistNotifier::class));

        $empty = new ContainerBuilder();
        $bundle->process($empty);
        self::assertFalse($empty->hasDefinition(WaitlistNotifier::class));
    }

    public function testTheBundleAddsItselfAsACompilerPass(): void
    {
        $container = new ContainerBuilder();
        $bundle = new PhilWaitlistBundle();
        $bundle->build($container);

        self::assertContains($bundle, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
    }

    public function testTheMappingIsPrependedForDoctrine(): void
    {
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
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        /** @var list<array{orm: array{mappings: array<string, array{type: string, is_bundle: bool, prefix: string, dir: string}>}}> $configs */
        $configs = $container->getExtensionConfig('doctrine');
        $mapping = $configs[0]['orm']['mappings']['PhilWaitlistBundle'];
        self::assertSame(['attribute', false, 'Phil\WaitlistBundle\Domain\Model'], [$mapping['type'], $mapping['is_bundle'], $mapping['prefix']]);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('doctrine'));
    }

    public function testTheAlias(): void
    {
        self::assertSame('phil_waitlist', $this->extension()->getAlias());
    }

    private function reference(Definition $definition, int $index): Reference
    {
        $argument = $definition->getArgument($index);
        self::assertInstanceOf(Reference::class, $argument);

        return $argument;
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
        $extension = new PhilWaitlistBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
