<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\DependencyInjection;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Phil\IncidentMode\IncidentMode;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Contract\SandboxPolicy;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\ModerationBundle\Application\FirstPostSandbox;
use Phil\ModerationBundle\Application\MemberNotes;
use Phil\ModerationBundle\Application\ModerationQueue;
use Phil\ModerationBundle\Application\ModerationTargets;
use Phil\ModerationBundle\Application\NoCurrentMember;
use Phil\ModerationBundle\Application\NoTrustedHiders;
use Phil\ModerationBundle\Application\NoWordScreen;
use Phil\ModerationBundle\Application\PublicJournal;
use Phil\ModerationBundle\Application\ReasonGrounds;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Redress;
use Phil\ModerationBundle\Application\Reports;
use Phil\ModerationBundle\Application\ReportSurge;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Application\StatementsOfReasons;
use Phil\ModerationBundle\Application\Submissions;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Contract\MemberNoteRepository;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\Contract\StatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationSchemaListener;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\Http\JournalController;
use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Phil\ModerationBundle\Infrastructure\Http\StatementController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentProposal;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementNotifier;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementSubjects;
use Phil\ModerationBundle\Infrastructure\Privacy\ModerationPrivacy;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentModerator;
use Phil\ModerationBundle\Infrastructure\Twig\KitComponents;
use Phil\ModerationBundle\Infrastructure\Twig\ModerationExtension;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the bundle, from its processed configuration.
 *
 * Every port has a default the application replaces by aliasing the interface to its own service: `CurrentMember` and
 * `CurrentModerator` read the security token, `WordScreen` lets every text through, `TrustedHiders` trusts nobody,
 * `SandboxPolicy` holds the first content of the configured types. With `phil/incident-mode-bundle` registered, the queue
 * offers its incident mode through `IncidentProposal`.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     reasons: list<string>,
 *     roles: list<array{name: string, attribute: string, types: list<string>}>,
 *     admin: array{layout: string, page_size: positive-int},
 *     journal: array{page_size: positive-int},
 *     sandbox: array{types: list<string>},
 *     surge: array{reports: int, within: int},
 *     statements: array{enabled: bool, grounds: array<string, array{ground: string, reference: string|null}>, terms_url: string|null, complaint_url: string|null, out_of_court: list<array{name: string, url: string}>, notification_type: string|null, page_size: positive-int},
 *     kit_subdirectory: string|null,
 * }
 */
final class ModerationServices
{
    /** The tag of the targets, which autoconfiguration gives every `ModerationTarget`. */
    public const string TARGET_TAG = 'phil_moderation.target';

    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    public const string SUBJECT_RESOLVER_TAG = 'phil_notifications.subject_resolver';

    /**
     * The parameter of `phil/incident-mode-bundle` that holds the role of its administration, set only while that
     * administration is on.
     */
    public const string INCIDENT_ROLE_PARAMETER = 'phil_incident_mode.admin.role';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public static function load(array $config, ContainerBuilder $container): void
    {
        /** @var Config $config */
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $clock = new Reference('clock');
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(ReportRepository::class, DbalReportRepository::class)->setArguments([$connection]);
        $container->register(DecisionRepository::class, DbalDecisionRepository::class)->setArguments([$connection]);
        $container->register(MemberNoteRepository::class, DbalMemberNoteRepository::class)->setArguments([$connection]);
        $container->register(SandboxRepository::class, DbalSandboxRepository::class)->setArguments([$connection]);
        $container->register(StatementRepository::class, DbalStatementRepository::class)->setArguments([$connection]);
        $container->register(ModerationSchemaListener::class, ModerationSchemaListener::class)
            ->setArguments([$connection])
            ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']);

        $container->register(Reasons::class, Reasons::class)->setArguments([$config['reasons']]);
        $container->register(Roles::class, Roles::class)->setArguments([array_map(
            static fn (array $role): Definition => new Definition(Role::class, [$role['name'], $role['attribute'], $role['types']]),
            $config['roles'],
        )]);
        $container->register(ModerationTargets::class, ModerationTargets::class)
            ->setArguments([new TaggedIteratorArgument(self::TARGET_TAG)]);

        $security = self::registered($container, 'SecurityBundle');
        self::people($container, $security);
        $container->setAlias(WordScreen::class, NoWordScreen::class);
        $container->register(NoWordScreen::class, NoWordScreen::class);
        $container->setAlias(TrustedHiders::class, NoTrustedHiders::class);
        $container->register(NoTrustedHiders::class, NoTrustedHiders::class);
        $container->setAlias(SandboxPolicy::class, FirstPostSandbox::class);
        $container->register(FirstPostSandbox::class, FirstPostSandbox::class)
            ->setArguments([new Reference(SandboxRepository::class), $config['sandbox']['types']]);

        $container->register(Reports::class, Reports::class)->setArguments([
            new Reference(ReportRepository::class), new Reference(ModerationTargets::class), new Reference(Reasons::class),
            new Reference(CurrentMember::class), $clock, $events,
        ]);
        $statements = self::statements($config['statements'], $container, $events);
        $container->register(Decisions::class, Decisions::class)->setArguments([
            new Reference(DecisionRepository::class), new Reference(ReportRepository::class), new Reference(SandboxRepository::class),
            new Reference(ModerationTargets::class), new Reference(CurrentModerator::class), $clock, $events,
            $statements ? new Reference(StatementsOfReasons::class) : null,
        ]);
        $container->register(ModerationQueue::class, ModerationQueue::class)->setArguments([
            new Reference(ReportRepository::class), new Reference(DecisionRepository::class), new Reference(ModerationTargets::class),
            new Reference(CurrentModerator::class),
        ]);
        $container->register(Submissions::class, Submissions::class)->setArguments([
            new Reference(Reports::class), new Reference(WordScreen::class), new Reference(SandboxPolicy::class), $events,
        ]);
        $container->register(TemporaryHides::class, TemporaryHides::class)->setArguments([
            new Reference(Reports::class), new Reference(ModerationTargets::class), new Reference(TrustedHiders::class),
            new Reference(CurrentMember::class), $events,
        ]);
        $container->register(MemberNotes::class, MemberNotes::class)->setArguments([
            new Reference(MemberNoteRepository::class), new Reference(CurrentModerator::class), $clock,
        ]);
        $container->register(PublicJournal::class, PublicJournal::class)->setArguments([new Reference(DecisionRepository::class)]);
        $container->register(ReportSurge::class, ReportSurge::class)->setArguments([
            new Reference(ReportRepository::class), $clock, $config['surge']['reports'], $config['surge']['within'],
        ]);

        self::incidents($container, $security);
        self::http($config, $container, $security, $statements);
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(ModerationExtension::class, ModerationExtension::class)
            ->setArguments([
                new Reference(Reasons::class), new Reference(TemporaryHides::class), new Reference('security.csrf.token_manager'),
                new Reference(KitComponents::class),
            ])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ModerationPrivacy::class, ModerationPrivacy::class)
                ->setArguments([
                    new Reference(ReportRepository::class), new Reference(DecisionRepository::class), new Reference(MemberNoteRepository::class),
                    new Reference(SandboxRepository::class), new Reference(StatementRepository::class),
                ])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }

    /**
     * The statements of reasons, unless the configuration turns them off, and the notification that tells a member of
     * theirs when `phil/notifications-bundle` is registered and the configuration names its type.
     *
     * @param array{enabled: bool, grounds: array<string, array{ground: string, reference: string|null}>, terms_url: string|null, complaint_url: string|null, out_of_court: list<array{name: string, url: string}>, notification_type: string|null, page_size: positive-int} $config
     */
    private static function statements(array $config, ContainerBuilder $container, Reference $events): bool
    {
        if (!$config['enabled']) {
            return false;
        }

        $container->register(ReasonGrounds::class, ReasonGrounds::class)->setArguments([$config['grounds']]);
        $container->register(Redress::class, Redress::class)->setArguments([$config['terms_url'], $config['complaint_url'], $config['out_of_court']]);
        $container->register(StatementsOfReasons::class, StatementsOfReasons::class)->setArguments([
            new Reference(StatementRepository::class), new Reference(Reasons::class), new Reference(ReasonGrounds::class),
            new Reference(CurrentMember::class), new Reference(Redress::class), $events,
        ]);

        if (null !== $config['notification_type'] && class_exists(Notifications::class) && self::registered($container, 'PhilNotificationsBundle')) {
            $container->register(StatementNotifier::class, StatementNotifier::class)
                ->setArguments([new Reference(Notifications::class), $config['notification_type']])
                ->addTag('kernel.event_listener', ['event' => StatementIssued::class]);
            $container->register(StatementSubjects::class, StatementSubjects::class)
                ->setArguments([new Reference('translator'), new Reference('router')])
                ->addTag(self::SUBJECT_RESOLVER_TAG);
        }

        return true;
    }

    /**
     * The members and moderators of the security token with SecurityBundle; nobody without it.
     */
    private static function people(ContainerBuilder $container, bool $security): void
    {
        if ($security) {
            $container->register(TokenCurrentMember::class, TokenCurrentMember::class)
                ->setArguments([new Reference('security.token_storage')]);
            $container->register(TokenCurrentModerator::class, TokenCurrentModerator::class)
                ->setArguments([new Reference(CurrentMember::class), new Reference('security.authorization_checker'), new Reference(Roles::class)]);
            $container->setAlias(CurrentMember::class, TokenCurrentMember::class);
            $container->setAlias(CurrentModerator::class, TokenCurrentModerator::class);

            return;
        }

        $container->register(NoCurrentMember::class, NoCurrentMember::class);
        $container->setAlias(CurrentMember::class, NoCurrentMember::class);
        $container->setAlias(CurrentModerator::class, NoCurrentMember::class);
    }

    /**
     * The incident mode of `phil/incident-mode-bundle`, when it is registered: its `IncidentMode` reads the incidents in
     * force for the proposal of the queue, and the role of its administration says who sees it — `ModerationPass` leaves
     * the proposal out when that administration is off. The storage, the presets, the commands, the page and the
     * checkpoints are the bundle's own.
     */
    private static function incidents(ContainerBuilder $container, bool $security): void
    {
        if (!$security || !class_exists(IncidentMode::class) || !self::registered($container, 'PhilIncidentModeBundle')) {
            return;
        }

        $container->register(IncidentProposal::class, IncidentProposal::class)->setArguments([
            new Reference(IncidentMode::class), new Reference(ReportSurge::class), new Reference('security.authorization_checker'),
            '%'.self::INCIDENT_ROLE_PARAMETER.'%',
        ]);
    }

    /**
     * @param Config $config
     */
    private static function http(array $config, ContainerBuilder $container, bool $security, bool $statements): void
    {
        $container->register(ReportController::class, ReportController::class)
            ->setArguments([new Reference(Reports::class), new Reference(TemporaryHides::class), new Reference('security.csrf.token_manager')])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(JournalController::class, JournalController::class)
            ->setArguments([new Reference(PublicJournal::class), new Reference('twig'), $config['admin']['layout'], $config['journal']['page_size']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if ($statements) {
            $container->register(StatementController::class, StatementController::class)
                ->setArguments([
                    new Reference(StatementsOfReasons::class), new Reference(CurrentMember::class), new Reference('twig'),
                    $config['admin']['layout'], $config['statements']['page_size'],
                ])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }

        // The pages of the moderation read the roles of the security token.
        if (!$security) {
            return;
        }

        $admin = $container->register(AdminController::class, AdminController::class)
            ->setArguments([
                new Reference(ModerationQueue::class), new Reference(Decisions::class), new Reference(MemberNotes::class),
                new Reference(CurrentModerator::class), new Reference('security.csrf.token_manager'), new Reference('twig'),
                new Reference('router'),
                $config['admin']['layout'], $config['admin']['page_size'],
                new Reference(IncidentProposal::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), $statements,
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        // The same pages grafted on every dashboard of an EasyAdmin back office, through #[AdminRoute].
        if (class_exists(EasyAdminBundle::class) && self::registered($container, 'EasyAdminBundle')) {
            $admin->addTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER);
        }
    }

    private static function registered(ContainerBuilder $container, string $bundle): bool
    {
        return $container->hasParameter('kernel.bundles') && \array_key_exists($bundle, (array) $container->getParameter('kernel.bundles'));
    }
}
