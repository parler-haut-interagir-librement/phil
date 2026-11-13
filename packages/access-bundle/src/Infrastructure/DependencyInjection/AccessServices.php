<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\DependencyInjection;

use DateInterval;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\AccessModel;
use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\NoCurrentMember;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Infrastructure\Console\PurgeCommand;
use Phil\AccessBundle\Infrastructure\EasyAdmin\EasyAdminAccessVoter;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Infrastructure\Security\AccessVoter;
use Phil\AccessBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\AccessBundle\Infrastructure\Session\SessionViewAsStore;
use Phil\AccessBundle\Infrastructure\Twig\AccessExtension;
use Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent;
use Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Dbal\TupleSchemaListener;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Tree\TreePermissions;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the store, the model and its checker and writer, the tree permissions, the spaces, items,
 * sharing, access groups and "view as", the voters, the controller, the Twig functions and components, and the purge
 * command — each integration only when its bundle is enabled.
 *
 * @phpstan-type Config array{
 *     store: string,
 *     connection: string,
 *     table: string,
 *     space: string,
 *     item_kinds: list<string>,
 *     sharing_levels: list<string>,
 *     default_visibility: string,
 *     groups: array<string, array{entry_points: list<string>, duration: ?string}>,
 *     budget: array{max_depth: int, max_reads: int, max_fan_out: int, max_candidates: int},
 *     current_member: ?string,
 *     view_as: array{redirect: string},
 *     easyadmin: array{actions: ?array<string, string>},
 *     kit_subdirectory: ?string,
 * }
 */
final class AccessServices
{
    public const string CONTRIBUTOR_TAG = 'phil_access.model_contributor';

    public const string CHANNEL = 'phil_access';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $levels = SharingLevels::of($config['sharing_levels']);
        $container->registerForAutoconfiguration(ModelContributor::class)->addTag(self::CONTRIBUTOR_TAG);

        self::registerModel($container, $config);
        self::registerApplication($container, $config);

        if (self::isEnabled($container, 'SecurityBundle')) {
            $container->register(AccessVoter::class, AccessVoter::class)
                ->setArguments([new Reference(ItemAccess::class), new Reference(SharingLevels::class), new Reference(ViewAs::class)])
                ->addTag('security.voter');
        }

        if (self::isEnabled($container, 'EasyAdminBundle')) {
            $container->register(EasyAdminAccessVoter::class, EasyAdminAccessVoter::class)
                ->setArguments([new Reference(ItemAccess::class), new Reference(SharingLevels::class), $config['easyadmin']['actions'] ?? self::defaultActions($levels)])
                ->addTag('security.voter');
        }

        if (self::isEnabled($container, 'TwigBundle')) {
            $container->register(AccessExtension::class, AccessExtension::class)
                ->setArguments([new Reference(ItemAccess::class), new Reference(Items::class), new Reference(AccessSubject::class), new Reference(ViewAs::class)])
                ->setAutoconfigured(true);
        }

        if (self::isEnabled($container, 'TwigComponentBundle')) {
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
            $container->register(ViewAsBannerComponent::class, ViewAsBannerComponent::class)
                ->setArguments([new Reference(ViewAs::class), new Reference(KitComponents::class), new Reference('router'), new Reference('security.csrf.token_manager')])
                ->setAutoconfigured(true);
            $container->register(AccessSummaryComponent::class, AccessSummaryComponent::class)
                ->setArguments([new Reference(ItemAccess::class), new Reference(Items::class), new Reference(Sharing::class), new Reference(SharingLevels::class), new Reference(KitComponents::class)])
                ->setAutoconfigured(true);
        }

        $container->register(ViewAsController::class, ViewAsController::class)
            ->setArguments([new Reference(ViewAs::class), new Reference('security.csrf.token_manager'), $config['view_as']['redirect']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([new Reference(TupleStore::class)])
            ->setAutoconfigured(true);
    }

    /**
     * The actions EasyAdmin runs and the level each needs, for the default levels; for others, seeing an entity alone.
     *
     * @return array<string, string>
     */
    public static function defaultActions(SharingLevels $levels): array
    {
        if (SharingLevels::DEFAULT === $levels->names) {
            return ['detail' => 'view', 'edit' => 'edit', 'delete' => 'manage'];
        }

        return ['detail' => $levels->narrowest()];
    }

    /**
     * @param Config $config
     */
    private static function registerModel(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(TupleStore::class, InMemoryTupleStore::class)->setArguments([new Reference('clock')])->setPublic(true);
        } else {
            $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
            $container->register(DbalTupleStore::class, DbalTupleStore::class)->setArguments([$connection, new Reference('clock'), $config['table']]);
            $container->setAlias(TupleStore::class, DbalTupleStore::class)->setPublic(true);
            $container->register(TupleSchemaListener::class, TupleSchemaListener::class)
                ->setArguments([$connection, new Reference(DbalTupleStore::class)])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
        }

        $budget = $config['budget'];
        $container->register(SharingLevels::class, SharingLevels::class)->setFactory([SharingLevels::class, 'of'])->setArguments([$config['sharing_levels']]);
        $container->register(Budget::class, Budget::class)->setArguments([$budget['max_depth'], $budget['max_reads'], $budget['max_fan_out'], $budget['max_candidates']]);
        $container->register(Model::class, Model::class)
            ->setFactory([AccessModel::class, 'create'])
            ->setArguments([$config['item_kinds'], new Reference(SharingLevels::class), new TaggedIteratorArgument(self::CONTRIBUTOR_TAG)])
            ->setPublic(true);
        $container->register(RelationChecker::class, RelationChecker::class)
            ->setArguments([new Reference(Model::class), new Reference(TupleStore::class), new Reference(Budget::class)]);
        $container->setAlias(RelationCheck::class, RelationChecker::class)->setPublic(true);
        $container->register(RelationWriter::class, RelationWriter::class)
            ->setArguments([new Reference(Model::class), new Reference(TupleStore::class)])
            ->setPublic(true);
        $container->register(TreePermissions::class, TreePermissions::class)
            ->setArguments([new Reference(TupleStore::class)])
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function registerApplication(ContainerBuilder $container, array $config): void
    {
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if (null !== $config['current_member']) {
            $container->setAlias(CurrentMember::class, $config['current_member']);
        } elseif (self::isEnabled($container, 'SecurityBundle')) {
            $container->register(TokenCurrentMember::class, TokenCurrentMember::class)->setArguments([new Reference('security.token_storage')]);
            $container->setAlias(CurrentMember::class, TokenCurrentMember::class);
        } else {
            $container->register(NoCurrentMember::class, NoCurrentMember::class);
            $container->setAlias(CurrentMember::class, NoCurrentMember::class);
        }

        $container->register(SessionViewAsStore::class, SessionViewAsStore::class)->setArguments([new Reference('request_stack')]);
        $container->setAlias(ViewAsStore::class, SessionViewAsStore::class);
        $container->register(Spaces::class, Spaces::class)
            ->setArguments([new Reference(RelationWriter::class), new Reference(RelationCheck::class), $config['space']])
            ->setPublic(true);
        $container->register(ViewAs::class, ViewAs::class)
            ->setArguments([new Reference(ViewAsStore::class), new Reference(CurrentMember::class), new Reference(Spaces::class), $logger, $events])
            ->addTag('monolog.logger', ['channel' => self::CHANNEL])
            ->setPublic(true);
        $container->register(AccessSubject::class, AccessSubject::class)
            ->setArguments([new Reference(CurrentMember::class), new Reference(ViewAs::class)]);
        $container->register(ItemAccess::class, ItemAccess::class)
            ->setArguments([new Reference(RelationCheck::class), new Reference(AccessSubject::class), new Reference(SharingLevels::class), $logger])
            ->addTag('monolog.logger', ['channel' => self::CHANNEL])
            ->setPublic(true);
        $container->register(Items::class, Items::class)
            ->setArguments([new Reference(RelationWriter::class), new Reference(TupleStore::class), new Reference(SharingLevels::class), new Reference(Spaces::class), Visibility::from($config['default_visibility'])])
            ->setPublic(true);

        $groups = [];

        foreach ($config['groups'] as $name => $group) {
            $groups[] = new Definition(AccessGroup::class, [
                $name,
                array_map(EntryPoint::from(...), $group['entry_points']),
                null === $group['duration'] ? null : new Definition(DateInterval::class, [$group['duration']]),
            ])->setFactory([AccessGroup::class, 'of']);
        }

        $container->register(AccessGroups::class, AccessGroups::class)
            ->setArguments([new Reference(RelationWriter::class), new Reference(TupleStore::class), new Reference('clock'), $groups, $events])
            ->setPublic(true);
        $container->register(Sharing::class, Sharing::class)
            ->setArguments([new Reference(RelationWriter::class), new Reference(TupleStore::class), new Reference(SharingLevels::class), new Reference(AccessGroups::class), new Reference('clock')])
            ->setPublic(true);
    }

    /**
     * Whether the kernel enables a bundle; read from the kernel's parameter, since the container an extension loads
     * into knows no other extension.
     */
    private static function isEnabled(ContainerBuilder $container, string $bundle): bool
    {
        $bundles = $container->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
