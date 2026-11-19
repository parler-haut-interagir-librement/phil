<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection;

use LogicException;
use Phil\DoctrineBehaviours\BinaryKeysListener;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\BlameListener;
use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\DoctrineBehaviours\Contract\BlameResolver;
use Phil\DoctrineBehaviours\Contract\OwnerResolver;
use Phil\DoctrineBehaviours\Contract\PersonalFields;
use Phil\DoctrineBehaviours\Contract\Publishable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Phil\DoctrineBehaviours\DatedIdentifierListener;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\DeactivationListener;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\FixedOwner;
use Phil\DoctrineBehaviours\NoPersonalFields;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\OwnerListener;
use Phil\DoctrineBehaviours\PersonalFieldPolicy;
use Phil\DoctrineBehaviours\PositionListener;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\PublicationListener;
use Phil\DoctrineBehaviours\PublicationWorkflow;
use Phil\DoctrineBehaviours\RefuseVersionRestore;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\SoftDeleteListener;
use Phil\DoctrineBehaviours\SoftDeletes;
use Phil\DoctrineBehaviours\TimestampListener;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\Tree;
use Phil\DoctrineBehaviours\TreeListener;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Phil\DoctrineBehaviours\VersionEraser;
use Phil\DoctrineBehaviours\VersionListener;
use Phil\DoctrineBehaviours\Versions;
use Phil\DoctrineBehavioursBundle\Infrastructure\Console\StripVersionsCommand;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\BlamePrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\OwnerPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\PersonalDataFields;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\VersionsPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityBlameResolver;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityOwnerResolver;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityVersionRestoreGuard;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Phil\Slug\Sluggers;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Workflow\Workflow;

/**
 * The listeners of the behaviours switched on, each on the events it needs, the resolvers and erasers, the readers,
 * the answers to phil/privacy-bundle, and what the behaviours add to the configuration of DoctrineBundle and
 * FrameworkBundle. The tree listener, which keeps the moves of a flush until it ends, and the suspension of the blame
 * during an erasure are reset with the kernel's services; no other service holds anything between requests. The
 * erasers reach every entity manager of the `doctrine` registry.
 *
 * @phpstan-type BehavioursConfig array{timestamps: bool, blame: array{enabled: bool, user_method: ?string, suspend_during_erasure: bool}, soft_delete: array{enabled: bool, filter: bool}, tree: bool, deactivation: array{enabled: bool, filter: bool}, owner: array{enabled: bool, user_method: ?string}, publication: array{enabled: bool, workflow: string, define_workflow: bool}, position: bool, translation: bool, slug: array{enabled: bool}, versions: array{enabled: bool, restore_right: string, personal_fields: string, cipher: ?string}, dated_identifier: array{enabled: bool}, privacy: array{enabled: bool, blame_replacement: ?string, owner_replacement: ?string}}
 */
final class BehaviourServices
{
    public const string FILTER = SoftDeletedFilter::NAME;

    public const string DEACTIVATED_FILTER = DeactivatedFilter::NAME;

    private const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    /**
     * The priority of `SoftDeleteListener` on `onFlush`, below every listener of the default priority: those that
     * must see a removal as a deletion, such as a search index that drops the entity, run before it turns the removal
     * into an update.
     */
    public const int SOFT_DELETE_PRIORITY = -256;

    private const string REGISTRY = 'doctrine';

    /**
     * @param BehavioursConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::listen($container->register(BinaryKeysListener::class, BinaryKeysListener::class), ['loadClassMetadata']);

        if ($config['timestamps']) {
            self::listen($container->register(TimestampListener::class, TimestampListener::class)->setArguments([new Reference('clock')]), ['prePersist', 'onFlush']);
        }

        if ($config['blame']['enabled']) {
            $container->register(BlameResolver::class, interface_exists(TokenStorageInterface::class) ? SecurityBlameResolver::class : FixedBlame::class)
                ->setArguments(interface_exists(TokenStorageInterface::class) ? [new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['blame']['user_method']] : []);
            $suspend = $config['blame']['suspend_during_erasure'] && $config['privacy']['enabled'] && interface_exists(PersonalDataEraser::class);

            if ($suspend) {
                $container->register(BlameSuspension::class, BlameSuspension::class)->addTag('kernel.reset', ['method' => 'reset']);
            }

            self::listen($container->register(BlameListener::class, BlameListener::class)->setArguments([new Reference(BlameResolver::class), $suspend ? new Reference(BlameSuspension::class) : null]), ['prePersist', 'onFlush']);
            $container->register(BlameEraser::class, BlameEraser::class)->setArguments([new Reference(self::REGISTRY)])->setPublic(true);
        }

        if ($config['soft_delete']['enabled']) {
            $container->register(SoftDeleteListener::class, SoftDeleteListener::class)
                ->setArguments([new Reference('clock')])
                ->addTag('doctrine.event_listener', ['event' => 'onFlush', 'priority' => self::SOFT_DELETE_PRIORITY]);
            $container->register(SoftDeletes::class, SoftDeletes::class)->setArguments([new Reference(self::REGISTRY), new Reference('clock')])->setPublic(true);
        }

        if ($config['tree']) {
            self::listen($container->register(TreeListener::class, TreeListener::class)->addTag('kernel.reset', ['method' => 'reset']), ['postPersist', 'onFlush', 'postFlush']);
            $container->register(Tree::class, Tree::class)->setArguments([new Reference('doctrine.orm.entity_manager')])->setPublic(true);
        }

        if ($config['deactivation']['enabled']) {
            self::listen($container->register(DeactivationListener::class, DeactivationListener::class)->setArguments([new Reference('clock')]), ['prePersist', 'onFlush']);
        }

        if ($config['owner']['enabled']) {
            self::registerOwner($container, (string) $config['owner']['user_method']);
        }

        if ($config['publication']['enabled']) {
            self::registerPublication($container, $config['publication']['workflow']);
        }

        if ($config['position']) {
            self::listen($container->register(PositionListener::class, PositionListener::class), ['onFlush']);
            $container->register(Positions::class, Positions::class)->setArguments([new Reference('doctrine.orm.entity_manager')])->setPublic(true);
        }

        if ($config['translation']) {
            self::listen($container->register(TranslationListener::class, TranslationListener::class), ['loadClassMetadata']);
            $container->register(Translations::class, Translations::class)->setArguments([new Reference('doctrine.orm.entity_manager')])->setPublic(true);
        }

        if ($config['slug']['enabled']) {
            self::registerSlug($container);
        }

        if ($config['versions']['enabled']) {
            self::registerVersions($container, $config['versions']);
        }

        if ($config['dated_identifier']['enabled']) {
            self::registerDatedIdentifier($container);
        }

        if ($config['privacy']['enabled'] && interface_exists(PersonalDataEraser::class)) {
            self::registerPrivacy($container, $config);
        }
    }

    /**
     * The blame, the owner and the versions in the access and erasure requests of phil/privacy-bundle, each when it
     * is on. The blame and the owner redact the versions of the person before they replace the name the versions are
     * found by.
     *
     * @param BehavioursConfig $config
     */
    private static function registerPrivacy(ContainerBuilder $container, array $config): void
    {
        $versions = $config['versions']['enabled'] ? new Reference(VersionEraser::class) : null;

        if ($config['blame']['enabled']) {
            $container->register(BlamePrivacy::class, BlamePrivacy::class)
                ->setArguments([new Reference(BlameEraser::class), $config['privacy']['blame_replacement'], $versions])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        if ($config['owner']['enabled']) {
            $replacement = $config['privacy']['owner_replacement'];
            $container->register(OwnerPrivacy::class, OwnerPrivacy::class)
                ->setArguments([new Reference(OwnerEraser::class), null === $replacement ? null : new Definition(OwnerId::class, [$replacement]), $versions])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        if ($versions instanceof Reference) {
            $container->register(VersionsPrivacy::class, VersionsPrivacy::class)
                ->setArguments([$versions])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }

    private static function registerOwner(ContainerBuilder $container, string $userMethod): void
    {
        $security = interface_exists(TokenStorageInterface::class);
        $container->register(OwnerResolver::class, $security ? SecurityOwnerResolver::class : FixedOwner::class)
            ->setArguments($security ? [new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), $userMethod] : []);
        self::listen($container->register(OwnerListener::class, OwnerListener::class)->setArguments([new Reference(OwnerResolver::class)]), ['prePersist']);
        $container->register(OwnerEraser::class, OwnerEraser::class)->setArguments([new Reference(self::REGISTRY)])->setPublic(true);
    }

    private static function registerPublication(ContainerBuilder $container, string $workflow): void
    {
        if (!class_exists(Workflow::class)) {
            throw new LogicException('The publication behaviour needs symfony/workflow: run "composer require symfony/workflow", or set phil_doctrine_behaviours.publication to false.');
        }

        $container->register(PublicationListener::class, PublicationListener::class)
            ->setArguments([new Reference('clock')])
            ->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.entered', $workflow), 'method' => '__invoke']);
    }

    private static function registerSlug(ContainerBuilder $container): void
    {
        self::listen($container->register(SlugListener::class, SlugListener::class)->setArguments([new Reference(Sluggers::class)]), ['onFlush']);
    }

    /**
     * @param array{enabled: bool, restore_right: string, personal_fields: string, cipher: ?string} $versions
     */
    private static function registerVersions(ContainerBuilder $container, array $versions): void
    {
        $security = interface_exists(AuthorizationCheckerInterface::class);
        $container->register(VersionRestoreGuard::class, $security ? SecurityVersionRestoreGuard::class : RefuseVersionRestore::class)
            ->setArguments($security ? [new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $versions['restore_right']] : []);
        self::listen($container->register(VersionListener::class, VersionListener::class), ['onFlush']);
        $container->register(PersonalFields::class, class_exists(PersonalData::class) ? PersonalDataFields::class : NoPersonalFields::class);
        $policy = PersonalFieldPolicy::from($versions['personal_fields']);
        $container->register(Versions::class, Versions::class)
            ->setArguments([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('clock'),
                new Reference(VersionRestoreGuard::class),
                new Reference(PersonalFields::class),
                $policy,
                PersonalFieldPolicy::Encrypt === $policy && null !== $versions['cipher'] ? new Reference($versions['cipher']) : null,
            ])
            ->setPublic(true);
        $container->register(VersionEraser::class, VersionEraser::class)
            ->setArguments([new Reference(self::REGISTRY), new Reference(PersonalFields::class), new Reference('clock')])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(StripVersionsCommand::class, StripVersionsCommand::class)
                ->setArguments([new Reference(VersionEraser::class)])
                ->addTag('console.command');
        }
    }

    private static function registerDatedIdentifier(ContainerBuilder $container): void
    {
        if (!class_exists(UuidV7::class)) {
            throw new LogicException('The dated identifier needs symfony/uid: run "composer require symfony/uid", or set phil_doctrine_behaviours.dated_identifier to false.');
        }

        self::listen($container->register(DatedIdentifierListener::class, DatedIdentifierListener::class)->setArguments([new Reference('clock')]), ['prePersist']);
    }

    /**
     * What the behaviours switched on add to DoctrineBundle: their filters, enabled on the default entity manager, and
     * the mapping of the frozen versions.
     *
     * @param BehavioursConfig $config
     *
     * @return array<string, mixed>
     */
    public static function doctrine(array $config): array
    {
        $filters = [];

        if ($config['soft_delete']['enabled'] && $config['soft_delete']['filter']) {
            $filters[self::FILTER] = ['class' => SoftDeletedFilter::class, 'enabled' => true];
        }

        if ($config['deactivation']['enabled'] && $config['deactivation']['filter']) {
            $filters[self::DEACTIVATED_FILTER] = ['class' => DeactivatedFilter::class, 'enabled' => true];
        }

        $orm = [] === $filters ? [] : ['filters' => $filters];

        if ($config['versions']['enabled']) {
            $version = new ReflectionClass(RecordVersion::class);
            $orm['mappings'] = ['PhilDoctrineBehaviours' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => \dirname((string) $version->getFileName()),
                'prefix' => $version->getNamespaceName(),
            ]];
        }

        return [] === $orm ? [] : ['orm' => $orm];
    }

    /**
     * The state machine of publication, declared for FrameworkBundle: a service of `workflow.registry`, so that
     * anything that applies transitions by name — a scheduler, a command — reaches it with no dependency on this bundle.
     *
     * @return array<string, mixed>
     */
    public static function workflow(string $name): array
    {
        return ['workflows' => ['enabled' => true, 'workflows' => [$name => [
            'type' => 'state_machine',
            'marking_store' => ['type' => 'method', 'property' => PublicationWorkflow::MARKING_PROPERTY],
            'supports' => [Publishable::class],
            'initial_marking' => [PublicationWorkflow::DRAFT],
            'places' => PublicationWorkflow::PLACES,
            'transitions' => PublicationWorkflow::TRANSITIONS,
        ]]]];
    }

    /**
     * @param list<string> $events
     */
    private static function listen(Definition $listener, array $events): void
    {
        foreach ($events as $event) {
            $listener->addTag('doctrine.event_listener', ['event' => $event]);
        }
    }
}
