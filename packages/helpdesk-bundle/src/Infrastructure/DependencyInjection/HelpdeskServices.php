<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Application\Conversations;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\HelpdeskBundle\Infrastructure\Http\AgentController;
use Phil\HelpdeskBundle\Infrastructure\Http\HelpdeskRequest;
use Phil\HelpdeskBundle\Infrastructure\Http\RequesterController;
use Phil\HelpdeskBundle\Infrastructure\Notifications\NotifyAssignee;
use Phil\HelpdeskBundle\Infrastructure\Privacy\HelpdeskPrivacy;
use Phil\HelpdeskBundle\Infrastructure\Security\RoleQueueAccess;
use Phil\HelpdeskBundle\Infrastructure\Twig\HelpdeskExtension;
use Phil\HelpdeskBundle\Infrastructure\Twig\KitComponents;
use Phil\HelpdeskBundle\Infrastructure\Twig\TwigCannedReplies;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The bundle's services: the DBAL store, the queues, who works them, the canned replies, the entry point, the pages of
 * the requesters and of the agents — grafted on the dashboards of EasyAdmin when it is registered —, the privacy part
 * when phil/privacy-bundle is installed and the notification of an assignment when phil/notifications-bundle is.
 *
 * @phpstan-import-type Declaration from TwigCannedReplies
 *
 * @phpstan-type Config array{connection: string, queues: array<string, array{label: string, roles: non-empty-list<string>}>, queue_access: ?string, canned_replies: array<string, Declaration>, canned_reply_source: ?string, layout: string, agent_layout: string, kit_subdirectory: ?string, notifications: array{enabled: bool, type: string}}
 */
final class HelpdeskServices
{
    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    /**
     * @param Config        $config
     * @param array<string> $bundles the names of the registered bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');

        $container->register(DbalTicketRepository::class, DbalTicketRepository::class)->setArguments([$connection]);
        $container->setAlias(TicketRepository::class, DbalTicketRepository::class)->setPublic(true);

        $container->register(Queues::class, Queues::class)
            ->setFactory([Queues::class, 'fromDeclarations'])
            ->setArguments([$config['queues']])
            ->setPublic(true);

        if (null === $config['queue_access']) {
            $container->register(RoleQueueAccess::class, RoleQueueAccess::class)
                ->setArguments([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $container->setAlias(QueueAccess::class, $config['queue_access'] ?? RoleQueueAccess::class);

        self::cannedReplies($container, $config);

        $container->register(Helpdesk::class, Helpdesk::class)
            ->setArguments([
                new Reference(TicketRepository::class),
                new Reference(Conversations::class),
                new Reference(Queues::class),
                new Reference(QueueAccess::class),
                new Reference('clock'),
                new Reference(CannedReplies::class),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(SchemaListener::class, SchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(HelpdeskPrivacy::class, HelpdeskPrivacy::class)
                ->setArguments([new Reference(TicketRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        if ($config['notifications']['enabled'] && class_exists(Notifications::class) && \in_array('PhilNotificationsBundle', $bundles, true)) {
            $container->register(NotifyAssignee::class, NotifyAssignee::class)
                ->setArguments([new Reference(Notifications::class), $config['notifications']['type']])
                ->addTag('kernel.event_listener', ['event' => TicketAssigned::class]);
        }

        self::pages($container, $config, $bundles);
    }

    /**
     * @param Config $config
     */
    private static function cannedReplies(ContainerBuilder $container, array $config): void
    {
        if (null !== $config['canned_reply_source']) {
            $container->setAlias(CannedReplies::class, $config['canned_reply_source']);

            return;
        }

        if ([] === $config['canned_replies']) {
            $container->register(NoCannedReplies::class, NoCannedReplies::class);
            $container->setAlias(CannedReplies::class, NoCannedReplies::class);

            return;
        }

        $container->register(TwigCannedReplies::class, TwigCannedReplies::class)
            ->setArguments([new Reference('twig'), $config['canned_replies']]);
        $container->setAlias(CannedReplies::class, TwigCannedReplies::class);
    }

    /**
     * @param Config        $config
     * @param array<string> $bundles
     */
    private static function pages(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!class_exists(Environment::class) || !interface_exists(UrlGeneratorInterface::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(HelpdeskExtension::class, HelpdeskExtension::class)
            ->setArguments([new Reference(KitComponents::class), new Reference(ParticipantDirectory::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $container->register(HelpdeskRequest::class, HelpdeskRequest::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(RequesterController::class, RequesterController::class)
            ->setArguments([
                new Reference(Helpdesk::class),
                new Reference(Conversations::class),
                new Reference(Queues::class),
                new Reference(CurrentParticipant::class),
                new Reference(HelpdeskRequest::class),
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $agents = $container->register(AgentController::class, AgentController::class)
            ->setArguments([
                new Reference(Helpdesk::class),
                new Reference(ConversationReader::class),
                new Reference(Queues::class),
                new Reference(CurrentParticipant::class),
                new Reference(HelpdeskRequest::class),
                new Reference('twig'),
                new Reference('router'),
                $config['agent_layout'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        // The same pages grafted on every dashboard of an EasyAdmin back office, through #[AdminRoute].
        if (class_exists(EasyAdminBundle::class) && \in_array('EasyAdminBundle', $bundles, true)) {
            $agents->addTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER);
        }
    }
}
