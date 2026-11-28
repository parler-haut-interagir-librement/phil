<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle;

use Override;
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
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Infrastructure\Console\LapseOffersCommand;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineEntryRepository;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineWaitlistRepository;
use Phil\WaitlistBundle\Integration\Notifications\WaitlistNotifier;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Waiting lists for anything with a capacity: the lists and their entries in Doctrine, the service that offers freed
 * places with a deadline under each list's lock, the command that lapses the offers whose deadline has come, and the
 * notifications of the participants when phil/notifications-bundle is enabled.
 *
 * ```yaml
 * phil_waitlist:
 *     offer_window: 172800
 *     max_places: 10
 *     policy: first_in_line
 * ```
 */
final class PhilWaitlistBundle extends AbstractBundle implements CompilerPassInterface
{
    /** The policies the configuration names, by name. */
    public const array POLICIES = ['first_in_line' => FirstInLine::class, 'first_that_fits' => FirstThatFits::class];

    #[Override]
    protected string $extensionAlias = 'phil_waitlist';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('offer_window')
                    ->info('How long an entry holds an offer, in seconds, for the lists opened without their own: from a minute to thirty days.')
                    ->defaultValue(172_800)
                    ->min(Waitlist::MIN_OFFER_WINDOW)
                    ->max(Waitlist::MAX_OFFER_WINDOW)
                ->end()
                ->integerNode('max_places')
                    ->info('The most places one entry asks for: from 1 to 1,000.')
                    ->defaultValue(10)
                    ->min(1)
                    ->max(1_000)
                ->end()
                ->enumNode('policy')
                    ->info('Which waiting entries are offered the places held: "first_in_line" strictly by rank, "first_that_fits" passing over those that need more. Alias OfferPolicy to a service of your own for another.')
                    ->values(array_keys(self::POLICIES))
                    ->defaultValue('first_in_line')
                ->end()
                ->integerNode('batch')
                    ->info('The most entries read at a time: waiting entries when places free up, offers and lists when offers lapse; from 1 to 10,000.')
                    ->defaultValue(100)
                    ->min(1)
                    ->max(10_000)
                ->end()
                ->arrayNode('notifications')
                    ->info('The notification type sent for each event when phil/notifications-bundle is enabled; null sends none.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('joined')->info('When an entry joins a list.')->defaultNull()->end()
                        ->scalarNode('offered')->info('When an entry is offered places.')->defaultValue('waitlist.offered')->end()
                        ->scalarNode('accepted')->info('When an entry accepts its offer.')->defaultNull()->end()
                        ->scalarNode('lapsed')->info("When an entry's offer lapses.")->defaultValue('waitlist.lapsed')->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass($this);
    }

    /**
     * Removes the notifier when the notifications bundle is installed but not enabled.
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(WaitlistNotifier::class) && !$container->has(Notifications::class)) {
            $container->removeDefinition(WaitlistNotifier::class);
        }
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilWaitlistBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\WaitlistBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{offer_window: int, max_places: int, policy: key-of<self::POLICIES>, batch: int, notifications: array{joined: ?string, offered: ?string, accepted: ?string, lapsed: ?string}} $config */
        $services = $container->services();

        // Each adapter is the default of the ports it implements; the service reads the ports, so that an application
        // that aliases one to its own adapter is heard.
        foreach ([DoctrineWaitlistRepository::class, DoctrineEntryRepository::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        foreach (self::POLICIES as $policy) {
            $services->set($policy);
        }

        $services->alias(OfferPolicy::class, self::POLICIES[$config['policy']]);

        $services->set(Waitlists::class)
            ->args([
                new Reference(WaitlistRepository::class),
                new Reference(EntryRepository::class),
                new Reference(OfferPolicy::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['offer_window'],
                $config['max_places'],
                $config['batch'],
            ])
            ->public();

        $services->set(LapseOffersCommand::class)
            ->args([new Reference(Waitlists::class)])
            ->tag('console.command');

        if (class_exists(Notifications::class)) {
            $this->notifications($config['notifications'], $container);
        }
    }

    /**
     * @param array{joined: ?string, offered: ?string, accepted: ?string, lapsed: ?string} $types
     */
    private function notifications(array $types, ContainerConfigurator $container): void
    {
        $byEvent = array_filter([
            EntryJoined::class => $types['joined'],
            PlaceOffered::class => $types['offered'],
            OfferAccepted::class => $types['accepted'],
            OfferLapsed::class => $types['lapsed'],
        ], static fn (?string $type): bool => null !== $type);

        if ([] === $byEvent) {
            return;
        }

        $notifier = $container->services()->set(WaitlistNotifier::class)
            ->args([
                new Reference(Notifications::class),
                new Reference(NotificationTypes::class),
                new Reference(EntryRepository::class),
                $byEvent,
            ]);

        foreach (array_keys($byEvent) as $event) {
            $notifier->tag('kernel.event_listener', ['event' => $event]);
        }
    }
}
