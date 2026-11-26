<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\DomainJoin;
use Phil\OrganizationsBundle\Application\InvitationPolicy;
use Phil\OrganizationsBundle\Application\Invitations;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Application\OnBehalf;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\Teams;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\MemberLeft;
use Phil\OrganizationsBundle\Domain\Event\MemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\TeamCreated;
use Phil\OrganizationsBundle\Domain\Event\TeamDeleted;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\TeamMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitCreated;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberAdded;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRemoved;
use Phil\OrganizationsBundle\Domain\Event\UnitMemberRolesChanged;
use Phil\OrganizationsBundle\Domain\Event\UnitMoved;
use Phil\OrganizationsBundle\Infrastructure\Access\MembershipTuples;
use Phil\OrganizationsBundle\Infrastructure\Access\OrganizationRelations;
use Phil\OrganizationsBundle\Infrastructure\Console\CheckOwnersCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ListOrganizationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\PurgeInvitationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ShowOrganizationCommand;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Doctrine\OrganizationSchemaListener;
use Phil\OrganizationsBundle\Infrastructure\Http\UnitAdminController;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Privacy\OrganizationsPrivacy;
use Phil\OrganizationsBundle\Infrastructure\Reporting\ReportingUnitDirectory;
use Phil\OrganizationsBundle\Infrastructure\Security\OrganizationVoter;
use Phil\OrganizationsBundle\Infrastructure\Security\UserIdentifierMember;
use Phil\OrganizationsBundle\Infrastructure\Twig\KitComponents;
use Phil\OrganizationsBundle\Infrastructure\Twig\OrganizationsExtension;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ResourcePermissions\RelationWriter;
use Phil\Slug\Slugger;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Config array{store: string, connection: string, clock: string, slugger: string|null, member_of_user: string|null, recommended_owners: positive-int, roles: array<string, array{permissions: list<string>}>, team_roles: array<string, array{permissions: list<string>}>, unit_roles: array<string, array{permissions: list<string>}>, units: array{single_membership: bool, layout: string, kit_subdirectory: string|null}, invitations: array{hash_key: non-empty-list<string>, default_lifetime: positive-int, max_lifetime: positive-int, default_uses: positive-int, max_uses: positive-int, purge_retention: non-negative-int}, domain_join: array{enabled: bool, roles: list<string>}, access: array{enabled: bool|null, organization_kind: string, team_kind: string, unit_kind: string|null}}
 */
final readonly class OrganizationsServices
{
    public const string STORE_DBAL = 'dbal';

    public const string STORE_MEMORY = 'memory';

    public const string ROLES = 'phil_organizations.roles';

    public const string TEAM_ROLES = 'phil_organizations.team_roles';

    public const string UNIT_ROLES = 'phil_organizations.unit_roles';

    public const string HASHER = 'phil_organizations.token_hasher';

    public const string SLUGGER = 'phil_organizations.slugger';

    public const string CONTRIBUTOR_TAG = 'phil_access.model_contributor';

    private const array TUPLE_EVENTS = [
        MemberJoined::class => 'onMemberJoined',
        MemberRolesChanged::class => 'onMemberRolesChanged',
        MemberLeft::class => 'onMemberLeft',
        TeamCreated::class => 'onTeamCreated',
        TeamDeleted::class => 'onTeamDeleted',
        TeamMemberAdded::class => 'onTeamMemberAdded',
        TeamMemberRolesChanged::class => 'onTeamMemberRolesChanged',
        TeamMemberRemoved::class => 'onTeamMemberRemoved',
        UnitCreated::class => 'onUnitCreated',
        UnitMoved::class => 'onUnitMoved',
        UnitMemberAdded::class => 'onUnitMemberAdded',
        UnitMemberRolesChanged::class => 'onUnitMemberRolesChanged',
        UnitMemberRemoved::class => 'onUnitMemberRemoved',
    ];

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::store($container, $config);
        self::catalogues($container, $config);

        $clock = new Reference($config['clock']);
        $store = new Reference(OrganizationStore::class);
        $work = new Reference(UnitOfWork::class);
        $access = new Reference(OrganizationAccess::class);
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $unitStore = new Reference(UnitStore::class);
        $optionalUnits = new Reference(UnitStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(UnitOfWork::class, UnitOfWork::class)->setArguments([$store, $events]);
        $container->register(OrganizationAccess::class, OrganizationAccess::class)->setArguments([$store, new Reference(self::ROLES)])->setPublic(true);
        $container->register(Organizations::class, Organizations::class)->setArguments([$store, $work, $access, new Reference(self::SLUGGER), $clock])->setPublic(true);
        $container->register(Members::class, Members::class)->setArguments([$store, $work, $access, $clock, $optionalUnits])->setPublic(true);
        $container->register(Teams::class, Teams::class)->setArguments([$store, $work, $access, new Reference(self::TEAM_ROLES), new Reference(self::SLUGGER), $clock])->setPublic(true);
        $container->register(Invitations::class, Invitations::class)
            ->setArguments([$store, $work, $access, new Reference(self::HASHER), $clock, new Reference(InvitationPolicy::class)])
            ->setPublic(true);
        $container->register(DomainJoin::class, DomainJoin::class)
            ->setArguments([$store, $work, $access, $clock, $config['domain_join']['enabled'], $config['domain_join']['roles']])
            ->setPublic(true);
        $container->register(OnBehalf::class, OnBehalf::class)->setArguments([$access, $clock, $events])->addTag('kernel.reset', ['method' => 'reset'])->setPublic(true);
        $container->register(OwnershipCheck::class, OwnershipCheck::class)->setArguments([$store, $config['recommended_owners']])->setPublic(true);
        $container->register(MemberErasure::class, MemberErasure::class)->setArguments([$store, $work, $clock, $optionalUnits])->setPublic(true);
        $container->register(UnitAccess::class, UnitAccess::class)->setArguments([$store, $unitStore, $access, new Reference(self::UNIT_ROLES)])->setPublic(true);
        $container->register(Units::class, Units::class)
            ->setArguments([$store, $unitStore, $work, $access, new Reference(UnitAccess::class), $clock, $config['units']['single_membership']])
            ->setPublic(true);
        $container->register(UnitTree::class, UnitTree::class)->setArguments([$unitStore])->setPublic(true);

        self::security($container, $config);
        self::pages($container, $config);
        self::console($container, $config);
        self::privacy($container);
        self::access($container, $config);
        self::reporting($container);
    }

    /**
     * The tree of units as the unit directory of phil/reporting-bundle, when it is installed: its training reports name
     * each unit and place it under its parent. An application that names its own directory keeps it.
     */
    private static function reporting(ContainerBuilder $container): void
    {
        if (interface_exists(UnitDirectory::class)) {
            $container->register(ReportingUnitDirectory::class, ReportingUnitDirectory::class)->setArguments([new Reference(UnitTree::class)]);
            $container->setAlias(UnitDirectory::class, ReportingUnitDirectory::class);
        }
    }

    /**
     * @param Config $config
     */
    private static function store(ContainerBuilder $container, array $config): void
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryOrganizationStore::class, InMemoryOrganizationStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(OrganizationStore::class, InMemoryOrganizationStore::class)->setPublic(true);
            $container->setAlias(UnitStore::class, InMemoryOrganizationStore::class)->setPublic(true);

            return;
        }

        if (self::STORE_DBAL !== $config['store']) {
            $container->setAlias(OrganizationStore::class, $config['store'])->setPublic(true);
            $container->setAlias(UnitStore::class, $config['store'])->setPublic(true);

            return;
        }

        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $container->register(DbalOrganizationStore::class, DbalOrganizationStore::class)->setArguments([$connection]);
        $container->setAlias(OrganizationStore::class, DbalOrganizationStore::class)->setPublic(true);
        $container->setAlias(UnitStore::class, DbalOrganizationStore::class)->setPublic(true);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(OrganizationSchemaListener::class, OrganizationSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
        }
    }

    /**
     * @param Config $config
     */
    private static function catalogues(ContainerBuilder $container, array $config): void
    {
        $permissions = static fn (array $roles): array => array_map(static fn (array $role): array => $role['permissions'], $roles);

        $container->register(self::ROLES, RoleCatalog::class)->setFactory([RoleCatalog::class, 'organization'])->setArguments([$permissions($config['roles'])]);
        $container->register(self::TEAM_ROLES, RoleCatalog::class)->setFactory([RoleCatalog::class, 'team'])->setArguments([$permissions($config['team_roles'])]);
        $container->register(self::UNIT_ROLES, RoleCatalog::class)->setFactory([RoleCatalog::class, 'unit'])->setArguments([$permissions($config['unit_roles'])]);
        $container->setAlias(RoleCatalog::class, self::ROLES)->setPublic(true);

        $container->register(self::HASHER, TokenHasher::class)->setArguments($config['invitations']['hash_key']);
        $container->register(InvitationPolicy::class, InvitationPolicy::class)->setArguments([
            $config['invitations']['default_lifetime'] * 86_400,
            $config['invitations']['max_lifetime'] * 86_400,
            $config['invitations']['default_uses'],
            $config['invitations']['max_uses'],
        ]);

        if (null === $config['slugger']) {
            $container->register(self::SLUGGER, TransliteratingSlugger::class);
        } else {
            $container->setAlias(self::SLUGGER, $config['slugger']);
        }
    }

    /**
     * @param Config $config
     */
    private static function security(ContainerBuilder $container, array $config): void
    {
        if (null === $config['member_of_user']) {
            $container->register(UserIdentifierMember::class, UserIdentifierMember::class);
            $container->setAlias(MemberOfUser::class, UserIdentifierMember::class);
        } else {
            $container->setAlias(MemberOfUser::class, $config['member_of_user']);
        }

        if (interface_exists(VoterInterface::class)) {
            $container->register(OrganizationVoter::class, OrganizationVoter::class)
                ->setArguments([new Reference(OrganizationAccess::class), new Reference(MemberOfUser::class), new Reference(UnitAccess::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('security.voter');
        }
    }

    /**
     * @param Config $config
     */
    private static function pages(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(Environment::class) || !interface_exists(CsrfTokenManagerInterface::class) || !interface_exists(TokenStorageInterface::class) || !class_exists(Response::class) || !interface_exists(UrlGeneratorInterface::class)) {
            return;
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['units']['kit_subdirectory']]);
        $container->register(OrganizationsExtension::class, OrganizationsExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(UnitAdminController::class, UnitAdminController::class)
            ->setArguments([
                new Reference(Units::class),
                new Reference(UnitTree::class),
                new Reference(OrganizationAccess::class),
                new Reference(UnitAccess::class),
                new Reference(MemberOfUser::class),
                new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('security.csrf.token_manager'),
                new Reference('twig'),
                new Reference('router'),
                $config['units']['layout'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function console(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        $container->register(ListOrganizationsCommand::class, ListOrganizationsCommand::class)
            ->setArguments([new Reference(Organizations::class)])
            ->addTag('console.command');
        $container->register(ShowOrganizationCommand::class, ShowOrganizationCommand::class)
            ->setArguments([new Reference(Organizations::class), new Reference(OrganizationStore::class), new Reference(OwnershipCheck::class), new Reference($config['clock'])])
            ->addTag('console.command');
        $container->register(PurgeInvitationsCommand::class, PurgeInvitationsCommand::class)
            ->setArguments([new Reference(Invitations::class), $config['invitations']['purge_retention']])
            ->addTag('console.command');
        $container->register(CheckOwnersCommand::class, CheckOwnersCommand::class)
            ->setArguments([new Reference(OwnershipCheck::class)])
            ->addTag('console.command');
    }

    private static function privacy(ContainerBuilder $container): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(OrganizationsPrivacy::class, OrganizationsPrivacy::class)
                ->setArguments([new Reference(OrganizationStore::class), new Reference(MemberErasure::class), new Reference(UnitStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('phil_privacy.exporter')
                ->addTag('phil_privacy.eraser');
        }
    }

    /**
     * @param Config $config
     */
    private static function access(ContainerBuilder $container, array $config): void
    {
        $installed = interface_exists(ModelContributor::class) && class_exists(RelationWriter::class);

        if (!($config['access']['enabled'] ?? ($installed && $container->hasExtension('phil_access')))) {
            return;
        }

        $container->register(OrganizationRelations::class, OrganizationRelations::class)
            ->setArguments([
                new Reference(self::ROLES),
                new Reference(self::TEAM_ROLES),
                $config['access']['organization_kind'],
                $config['access']['team_kind'],
                null === $config['access']['unit_kind'] ? null : new Reference(self::UNIT_ROLES),
                $config['access']['unit_kind'],
            ])
            ->addTag(self::CONTRIBUTOR_TAG);
        $tuples = $container->register(MembershipTuples::class, MembershipTuples::class)
            ->setArguments([new Reference(RelationWriter::class), new Reference(OrganizationRelations::class)]);

        foreach (self::TUPLE_EVENTS as $event => $method) {
            $tuples->addTag('kernel.event_listener', ['event' => $event, 'method' => $method]);
        }
    }
}
