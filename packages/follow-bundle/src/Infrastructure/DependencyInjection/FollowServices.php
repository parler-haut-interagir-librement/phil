<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\FollowBundle\Application\Activities;
use Phil\FollowBundle\Application\AutoFollow;
use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Handler\PurgeFollowRecordsHandler;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Application\Message\PurgeFollowRecords;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Application\NoParents;
use Phil\FollowBundle\Application\OpenAccess;
use Phil\FollowBundle\Application\OpenFollowing;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Contract\FollowerVisibilityRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalActivityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalDeliveryRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowerVisibilityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRequestRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalMutualRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowSchemaListener;
use Phil\FollowBundle\Infrastructure\Http\FollowController;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Phil\FollowBundle\Infrastructure\Privacy\FollowPrivacy;
use Phil\FollowBundle\Infrastructure\Scheduler\FollowSchedule;
use Phil\FollowBundle\Infrastructure\Twig\FollowExtension;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The bundle's services: each port on what the application names, else on the safe default, and each integration —
 * the schema listener, the forms and templates, the schedule, the privacy requests — only when what it needs is there.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     member_subject_type: string,
 *     batch_size: int,
 *     message_bus: ?string,
 *     current_member: ?string,
 *     access: ?string,
 *     approval: ?string,
 *     parents: ?string,
 *     inheritance: array{max_depth: int},
 *     events: list<string>,
 *     auto_follow: array{created: bool, replied: bool, level: string},
 *     mutual: array{enabled: bool, follow_each_other: bool},
 *     followers: array{hidden_by_default: bool},
 *     email: array{once_until_seen: bool, resend_after: int},
 *     notifications: array{declare_types: bool, activity: string, quiet: string, requested: string, accepted: string, followed: string},
 *     retention: array{activity_days: int},
 *     schedule: array{enabled: bool, at: string, timezone: string, lock_factory: ?string, state_pool: ?string},
 * }
 */
final class FollowServices
{
    private const string EXPORTER_TAG = 'phil_privacy.exporter';

    private const string ERASER_TAG = 'phil_privacy.eraser';

    private const string ACCESS_REQUIRED = 'The option "phil_follow.access" is required with Symfony Security: name the FollowAccess service that says who may follow a subject and hear of its activity. "'.OpenAccess::class.'" lets every member follow every subject, for an application whose subjects are all public.';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::repositories($container, $config['connection']);
        self::ports($container, $config);
        self::application($container, $config);
        self::integrations($container, $config);
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

    private static function repositories(ContainerBuilder $container, string $connection): void
    {
        $dbal = new Reference('doctrine.dbal.'.$connection.'_connection');

        foreach ([
            FollowRepository::class => DbalFollowRepository::class,
            FollowRequestRepository::class => DbalFollowRequestRepository::class,
            MutualRepository::class => DbalMutualRepository::class,
            ActivityRepository::class => DbalActivityRepository::class,
            DeliveryRepository::class => DbalDeliveryRepository::class,
            FollowerVisibilityRepository::class => DbalFollowerVisibilityRepository::class,
        ] as $port => $adapter) {
            $container->register($adapter, $adapter)->setArguments([$dbal]);
            $container->setAlias($port, $adapter);
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(FollowSchemaListener::class, FollowSchemaListener::class)
                ->setArguments([$dbal])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $connection]);
        }
    }

    /**
     * @param Config $config
     *
     * @throws InvalidConfigurationException when Security is there and no access is named
     */
    private static function ports(ContainerBuilder $container, array $config): void
    {
        if (null === $config['access'] && self::has($container, 'security', 'SecurityBundle')) {
            throw new InvalidConfigurationException(self::ACCESS_REQUIRED);
        }

        foreach ([
            SubjectParents::class => [$config['parents'], NoParents::class],
            FollowApproval::class => [$config['approval'], OpenFollowing::class],
            FollowAccess::class => [$config['access'], OpenAccess::class],
        ] as $port => [$named, $default]) {
            if (null === $named || $default === $named) {
                $container->register($default, $default);
            }

            $container->setAlias($port, $named ?? $default);
        }

        $notifications = $config['notifications'];
        $container->register(FollowTypes::class, FollowTypes::class)
            ->setArguments([$notifications['activity'], $notifications['quiet'], $notifications['requested'], $notifications['accepted'], $notifications['followed']]);
        $container->register(SubjectChain::class, SubjectChain::class)
            ->setArguments([new Reference(SubjectParents::class), $config['inheritance']['max_depth']]);
    }

    /**
     * @param Config $config
     */
    private static function application(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference('clock');
        $notifications = new Reference(Notifications::class);
        $types = new Reference(FollowTypes::class);
        $member = $config['member_subject_type'];
        $bus = new Reference($config['message_bus'] ?? 'messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(Follows::class, Follows::class)
            ->setArguments([
                new Reference(FollowRepository::class),
                new Reference(FollowRequestRepository::class),
                new Reference(DeliveryRepository::class),
                new Reference(FollowApproval::class),
                new Reference(FollowAccess::class),
                new Reference(SubjectChain::class),
                $notifications,
                $clock,
                $types,
                $member,
            ])
            ->setPublic(true);
        $container->register(FollowRequests::class, FollowRequests::class)
            ->setArguments([
                new Reference(FollowRequestRepository::class),
                new Reference(FollowRepository::class),
                new Reference(MutualRepository::class),
                $notifications,
                $clock,
                $types,
                $member,
                $config['mutual']['follow_each_other'],
            ])
            ->setPublic(true);
        $container->register(Mutuals::class, Mutuals::class)
            ->setArguments([
                new Reference(MutualRepository::class),
                new Reference(FollowRequestRepository::class),
                new Reference(FollowRequests::class),
                $notifications,
                $clock,
                $config['mutual']['enabled'],
                $types,
                $member,
            ])
            ->setPublic(true);
        $container->register(Followers::class, Followers::class)
            ->setArguments([new Reference(FollowRepository::class), new Reference(FollowerVisibilityRepository::class), $config['followers']['hidden_by_default'], $member])
            ->setPublic(true);
        $container->register(AutoFollow::class, AutoFollow::class)
            ->setArguments([
                new Reference(FollowRepository::class),
                new Reference(FollowApproval::class),
                new Reference(FollowAccess::class),
                $clock,
                $config['auto_follow']['created'],
                $config['auto_follow']['replied'],
                FollowLevel::from($config['auto_follow']['level']),
                $member,
            ])
            ->setPublic(true);

        $container->register(FanOutActivityHandler::class, FanOutActivityHandler::class)
            ->setArguments([
                new Reference(ActivityRepository::class),
                new Reference(FollowRepository::class),
                new Reference(DeliveryRepository::class),
                new Reference(SubjectChain::class),
                new Reference(FollowAccess::class),
                $notifications,
                $clock,
                $types,
                $config['batch_size'],
                $config['email']['once_until_seen'],
                $config['email']['resend_after'],
                $bus,
            ])
            ->addTag('messenger.message_handler', ['handles' => FanOutActivity::class]);
        $container->register(PurgeFollowRecordsHandler::class, PurgeFollowRecordsHandler::class)
            ->setArguments([new Reference(ActivityRepository::class), new Reference(DeliveryRepository::class), $clock, $config['retention']['activity_days'], $config['email']['resend_after']])
            ->addTag('messenger.message_handler', ['handles' => PurgeFollowRecords::class])
            ->setPublic(true);
        $container->register(Activities::class, Activities::class)
            ->setArguments([new Reference(ActivityRepository::class), new Reference(FanOutActivityHandler::class), $clock, $bus])
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function integrations(ContainerBuilder $container, array $config): void
    {
        if (self::has($container, 'twig', 'TwigBundle')) {
            $currentMember = new Reference($config['current_member'] ?? CurrentRecipient::class);
            $container->register(FollowGuard::class, FollowGuard::class)
                ->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER), new Reference('uri_signer'), new Reference('router')]);
            $container->register(FollowController::class, FollowController::class)
                ->setArguments([
                    new Reference(Follows::class),
                    new Reference(FollowRequests::class),
                    new Reference(Followers::class),
                    new Reference(Mutuals::class),
                    $currentMember,
                    new Reference(FollowGuard::class),
                    new Reference('twig'),
                    new Reference('translator'),
                ])
                ->setPublic(true);
            $container->register(FollowExtension::class, FollowExtension::class)
                ->setArguments([
                    new Reference(Follows::class),
                    new Reference(FollowRequests::class),
                    new Reference(Followers::class),
                    new Reference(Mutuals::class),
                    $currentMember,
                    new Reference(FollowGuard::class),
                    new Reference('request_stack'),
                    new Reference(RecipientDirectory::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $config['events'],
                ])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        if ($config['schedule']['enabled'] && interface_exists(ScheduleProviderInterface::class)) {
            $schedule = $config['schedule'];
            $container->register(FollowSchedule::class, FollowSchedule::class)
                ->setArguments([
                    $schedule['at'],
                    $schedule['timezone'],
                    null === $schedule['lock_factory'] ? null : new Reference($schedule['lock_factory'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    null === $schedule['state_pool'] ? null : new Reference($schedule['state_pool'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('scheduler.schedule_provider', ['name' => FollowSchedule::NAME]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(FollowPrivacy::class, FollowPrivacy::class)
                ->setArguments([
                    new Reference(FollowRepository::class),
                    new Reference(FollowRequestRepository::class),
                    new Reference(MutualRepository::class),
                    new Reference(ActivityRepository::class),
                    new Reference(DeliveryRepository::class),
                    new Reference(FollowerVisibilityRepository::class),
                    $config['member_subject_type'],
                ])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }
}
