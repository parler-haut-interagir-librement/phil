<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle;

use Override;
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
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Workshops, their sessions and the registrations to them: all three in Doctrine, the service that registers and
 * cancels under each session's lock, the refusals in the reader's language, and, when phil/waitlist-bundle is enabled,
 * a waiting list per session that offers the seats freed in turn.
 *
 * ```yaml
 * phil_workshops:
 *     max_seats: 10
 *     waitlist: true
 * ```
 */
final class PhilWorkshopsBundle extends AbstractBundle implements CompilerPassInterface
{
    /** The events that keep a session's waiting list in step with its seats. */
    public const array WAITLIST_EVENTS = [RegistrationCancelled::class, SessionResized::class, SessionCancelled::class, EntryLeft::class];

    #[Override]
    protected string $extensionAlias = 'phil_workshops';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('max_seats')
                    ->info('The most seats one registration takes: from 1 to 1,000.')
                    ->defaultValue(10)
                    ->min(1)
                    ->max(1_000)
                ->end()
                ->booleanNode('waitlist')
                    ->info('Whether each session has a waiting list when phil/waitlist-bundle is enabled; false refuses a full session without one.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass($this);
    }

    /**
     * Removes the waiting list integration when phil/waitlist-bundle is installed but not enabled.
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WaitlistSeatClaims::class) || $container->has(Waitlists::class)) {
            return;
        }

        foreach ([WaitlistSeatClaims::class, SessionWaitlists::class, SessionWaitlistListener::class] as $id) {
            $container->removeDefinition($id);
        }

        $container->removeAlias(SeatClaims::class);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilWorkshopsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\WorkshopsBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{max_seats: int, waitlist: bool} $config */
        $services = $container->services();

        // Each adapter is the default of the ports it implements; the service reads the ports, so that an application
        // that aliases one to its own adapter is heard.
        foreach ([DoctrineWorkshopRepository::class, DoctrineSessionRepository::class, DoctrineRegistrationRepository::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        $services->set(Workshops::class)
            ->args([
                new Reference(WorkshopRepository::class),
                new Reference(SessionRepository::class),
                new Reference(RegistrationRepository::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['max_seats'],
                new Reference(SeatClaims::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();
        $services->set(RefusalMessages::class)->args([new Reference('translator')])->public();

        if ($config['waitlist'] && class_exists(Waitlists::class)) {
            $this->waitlist($config['max_seats'], $container);
        }
    }

    /**
     * The waiting list of each session, through phil/waitlist-bundle.
     */
    private function waitlist(int $maxSeats, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services->set(WaitlistSeatClaims::class)->args([new Reference(Waitlists::class)]);
        $services->alias(SeatClaims::class, WaitlistSeatClaims::class);

        $services->set(SessionWaitlists::class)
            ->args([
                new Reference(SessionRepository::class),
                new Reference(RegistrationRepository::class),
                new Reference(Waitlists::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $maxSeats,
            ])
            ->public();

        $listener = $services->set(SessionWaitlistListener::class)->args([new Reference(SessionWaitlists::class)]);

        foreach (self::WAITLIST_EVENTS as $event) {
            $listener->tag('kernel.event_listener', ['event' => $event]);
        }
    }
}
