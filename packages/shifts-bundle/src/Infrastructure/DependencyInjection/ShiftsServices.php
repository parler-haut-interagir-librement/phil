<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Allocation\InterestAllocator;
use Phil\Shifts\Roster\Conflicts;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Phil\ShiftsBundle\Application\Contract\Transaction;
use Phil\ShiftsBundle\Application\Message\AllocateInterest;
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Domain\Repository\AbsenceRepository;
use Phil\ShiftsBundle\Domain\Repository\GuardianRepository;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;
use Phil\ShiftsBundle\Infrastructure\Console\ImportPlanCommand;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalAbsenceRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalGuardianRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalRosterRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalSwapRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalTransaction;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsSchemaListener;
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;
use Phil\ShiftsBundle\Infrastructure\Http\Access;
use Phil\ShiftsBundle\Infrastructure\Http\CoordinatorController;
use Phil\ShiftsBundle\Infrastructure\Http\Pages;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Phil\ShiftsBundle\Infrastructure\Http\SwapController;
use Phil\ShiftsBundle\Infrastructure\Http\VolunteerController;
use Phil\ShiftsBundle\Infrastructure\Messenger\AllocateInterestHandler;
use Phil\ShiftsBundle\Infrastructure\Privacy\ShiftsPrivacy;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Phil\ShiftsBundle\Infrastructure\Security\TokenCurrentPerson;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\MyShiftsComponent;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\ShiftCardComponent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bundle's services: the repositories on the configured connection, the application services, and each
 * integration — the schema listener, the pages and their form, the components, the Messenger handler, the command,
 * the privacy requests — only when what it needs is there. Every service keeps no state between requests.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     person: ?string,
 *     allocator: ?string,
 *     pages: bool,
 *     time_zone: string,
 *     rest_minutes: int,
 *     roles: array{volunteer: ?string, coordinator: string},
 *     minors: array{birth_dates: ?string, age_of_majority: int},
 *     absences: array{threshold: ?int, window_days: int},
 * }
 */
final class ShiftsServices
{
    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $dbal = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $clock = new Reference('clock');
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(DbalRosterRepository::class, DbalRosterRepository::class)->setArguments([$dbal, $clock]);
        $container->setAlias(RosterRepository::class, DbalRosterRepository::class)->setPublic(true);
        $container->register(DbalSwapRepository::class, DbalSwapRepository::class)->setArguments([$dbal]);
        $container->setAlias(SwapRepository::class, DbalSwapRepository::class)->setPublic(true);
        $container->register(DbalGuardianRepository::class, DbalGuardianRepository::class)->setArguments([$dbal]);
        $container->setAlias(GuardianRepository::class, DbalGuardianRepository::class)->setPublic(true);
        $container->register(DbalAbsenceRepository::class, DbalAbsenceRepository::class)->setArguments([$dbal]);
        $container->setAlias(AbsenceRepository::class, DbalAbsenceRepository::class)->setPublic(true);
        $container->register(DbalTransaction::class, DbalTransaction::class)->setArguments([$dbal]);
        $container->setAlias(Transaction::class, DbalTransaction::class);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(ShiftsSchemaListener::class, ShiftsSchemaListener::class)
                ->setArguments([$dbal])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(Conflicts::class, Conflicts::class)->setArguments([$config['rest_minutes']]);
        $container->register(FairAllocator::class, FairAllocator::class);
        $container->setAlias(InterestAllocator::class, $config['allocator'] ?? FairAllocator::class);

        $birthDates = null === $config['minors']['birth_dates'] ? null : new Reference($config['minors']['birth_dates']);
        $container->register(Minors::class, Minors::class)
            ->setArguments([new Reference(GuardianRepository::class), $clock, $birthDates, $config['minors']['age_of_majority'], $config['time_zone']])
            ->setPublic(true);
        $container->register(Absences::class, Absences::class)
            ->setArguments([new Reference(AbsenceRepository::class), new Reference(RosterRepository::class), $clock, $config['absences']['threshold'], $config['absences']['window_days']])
            ->setPublic(true);
        $container->register(Rosters::class, Rosters::class)
            ->setArguments([new Reference(RosterRepository::class), $clock, new Reference(Conflicts::class), new Reference(InterestAllocator::class), $events, new Reference(Minors::class), new Reference(Absences::class)])
            ->setPublic(true);
        $container->register(SwapBoard::class, SwapBoard::class)
            ->setArguments([new Reference(SwapRepository::class), new Reference(RosterRepository::class), new Reference(Rosters::class), new Reference(Transaction::class), $clock, new Reference(Conflicts::class), $events])
            ->setPublic(true);
        $container->register(ShiftsVoter::class, ShiftsVoter::class)
            ->setArguments([new Reference('security.access.decision_manager'), $config['roles']['volunteer'], $config['roles']['coordinator']])
            ->addTag('security.voter');

        self::integrations($container, $config);
    }

    /**
     * @param Config $config
     */
    private static function integrations(ContainerBuilder $container, array $config): void
    {
        $twig = $config['pages'] && self::has($container, 'twig', 'TwigBundle');

        if ($twig) {
            if (null === $config['person']) {
                $container->register(TokenCurrentPerson::class, TokenCurrentPerson::class)->setArguments([new Reference('security.token_storage')]);
            }

            $container->setAlias(CurrentPerson::class, $config['person'] ?? TokenCurrentPerson::class);
            $container->register(ShiftsGuard::class, ShiftsGuard::class)->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER)]);
            $container->register(Access::class, Access::class)->setArguments([new Reference(CsrfProtectionPass::AUTHORIZATION), new Reference(CurrentPerson::class)]);
            $container->register(Pages::class, Pages::class)->setArguments([new Reference('twig'), new Reference('router'), new Reference(ShiftsGuard::class), $config['time_zone']]);
            $shared = [new Reference(Access::class), new Reference(Pages::class)];
            $container->register(VolunteerController::class, VolunteerController::class)->setArguments([new Reference(Rosters::class), ...$shared])->setPublic(true);
            $container->register(SwapController::class, SwapController::class)->setArguments([new Reference(SwapBoard::class), ...$shared])->setPublic(true);
            $container->register(CoordinatorController::class, CoordinatorController::class)
                ->setArguments([new Reference(Rosters::class), new Reference(SwapBoard::class), new Reference(CsrfProtectionPass::FORM_FACTORY), ...$shared, new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(Absences::class)])
                ->setPublic(true);
        }

        if (class_exists(AbstractType::class)) {
            $container->register(ShiftType::class, ShiftType::class)->setArguments([$config['time_zone']])->addTag('form.type');
        }

        if ($twig && class_exists(AsTwigComponent::class)) {
            $container->register(ShiftCardComponent::class, ShiftCardComponent::class)
                ->setArguments([new Reference(Rosters::class), new Reference(CurrentPerson::class), new Reference(ShiftsGuard::class), $config['time_zone']])
                ->setShared(false)
                ->addTag('twig.component', ['key' => 'PhilShifts:ShiftCard', 'template' => '@PhilShifts/components/ShiftCard.html.twig', 'expose_public_props' => false]);
            $container->register(MyShiftsComponent::class, MyShiftsComponent::class)
                ->setArguments([new Reference(Rosters::class), new Reference(CurrentPerson::class), new Reference('clock'), $config['time_zone']])
                ->setShared(false)
                ->addTag('twig.component', ['key' => 'PhilShifts:MyShifts', 'template' => '@PhilShifts/components/MyShifts.html.twig', 'expose_public_props' => false]);
        }

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(AllocateInterestHandler::class, AllocateInterestHandler::class)
                ->setArguments([new Reference(Rosters::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('messenger.message_handler', ['handles' => AllocateInterest::class]);
        }

        if (class_exists(Command::class)) {
            $container->register(ImportPlanCommand::class, ImportPlanCommand::class)->setArguments([new Reference(Rosters::class)])->addTag('console.command');
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ShiftsPrivacy::class, ShiftsPrivacy::class)
                ->setArguments([new Reference(RosterRepository::class), new Reference(SwapRepository::class), new Reference(GuardianRepository::class), new Reference(AbsenceRepository::class)])
                ->addTag('phil_privacy.exporter')
                ->addTag('phil_privacy.eraser');
        }
    }

    /**
     * Whether the application has a bundle: its extension, in a container that has them all, else its name among the
     * kernel's bundles, which is all an extension sees of the others while it loads.
     */
    private static function has(ContainerBuilder $container, string $extension, string $bundle): bool
    {
        if ($container->hasExtension($extension)) {
            return true;
        }

        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
