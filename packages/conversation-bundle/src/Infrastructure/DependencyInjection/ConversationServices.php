<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Application\ConversationLimits;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Application\IdentifierDirectory;
use Phil\ConversationBundle\Application\NoContacts;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\Repository\ReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Phil\ConversationBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Phil\ConversationBundle\Infrastructure\Http\ConversationRequest;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Phil\ConversationBundle\Infrastructure\Mercure\MercureMessagePublisher;
use Phil\ConversationBundle\Infrastructure\Notifications\ConversationSubjects;
use Phil\ConversationBundle\Infrastructure\Notifications\NotifyParticipants;
use Phil\ConversationBundle\Infrastructure\Privacy\ConversationPrivacy;
use Phil\ConversationBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\ConversationBundle\Infrastructure\Twig\ConversationExtension;
use Phil\ConversationBundle\Infrastructure\Twig\KitComponents;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The bundle's services: the sealer of Symfony's KeyManagement component, the DBAL stores and their rotation, the
 * entry points, the pages and their Twig functions; the privacy part when phil/privacy-bundle is installed, the
 * notifications when phil/notifications-bundle is, and the Mercure updates when they are enabled.
 *
 * @phpstan-type Config array{connection: string, encryption: array{key: string, kms: ?string}, current_participant: ?string, directory: ?string, contacts: ?string, layout: string, kit_subdirectory: ?string, limits: array{body_length: positive-int, subject_length: positive-int, group_size: int<2, max>, page_size: positive-int}, notifications: array{enabled: bool, type: string}, realtime: array{enabled: bool, hub: string, topic_prefix: string}}
 */
final class ConversationServices
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

        $container->register(KeyManagementMessageSealer::class, KeyManagementMessageSealer::class)
            ->setArguments([new Reference($config['encryption']['kms'] ?? DataKeyGeneratorInterface::class), $config['encryption']['key']]);
        $container->setAlias(MessageSealer::class, KeyManagementMessageSealer::class);

        $container->register(DbalConversationRepository::class, DbalConversationRepository::class)
            ->setArguments([$connection, new Reference(MessageSealer::class)]);
        $container->setAlias(ConversationRepository::class, DbalConversationRepository::class)->setPublic(true);
        $container->register(DbalReceptionRepository::class, DbalReceptionRepository::class)->setArguments([$connection]);
        $container->setAlias(ReceptionRepository::class, DbalReceptionRepository::class)->setPublic(true);

        self::ports($container, $config);

        $container->register(Reception::class, Reception::class)
            ->setArguments([new Reference(ReceptionRepository::class), new Reference(ContactRelation::class)])
            ->setPublic(true);
        $container->register(ConversationLimits::class, ConversationLimits::class)
            ->setArguments([$config['limits']['body_length'], $config['limits']['subject_length'], $config['limits']['group_size'], $config['limits']['page_size']]);
        $container->register(Conversations::class, Conversations::class)
            ->setArguments([
                new Reference(ConversationRepository::class),
                new Reference(Reception::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ConversationLimits::class),
            ])
            ->setPublic(true);
        $container->setAlias(ConversationReader::class, Conversations::class)->setPublic(true);

        $container->register(KeyRotation::class, KeyRotation::class)->setArguments([$connection, new Reference(MessageSealer::class)])->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(RotateKeysCommand::class, RotateKeysCommand::class)
                ->setArguments([new Reference(KeyRotation::class)])
                ->addTag('console.command');
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(SchemaListener::class, SchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ConversationPrivacy::class, ConversationPrivacy::class)
                ->setArguments([new Reference(ConversationRepository::class), new Reference(ReceptionRepository::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        self::notifications($container, $config, $bundles);
        self::realtime($container, $config);
        self::pages($container, $config);
    }

    /**
     * @param Config $config
     */
    private static function ports(ContainerBuilder $container, array $config): void
    {
        if (null === $config['current_participant']) {
            $container->register(SecurityCurrentParticipant::class, SecurityCurrentParticipant::class)
                ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $container->setAlias(CurrentParticipant::class, $config['current_participant'] ?? SecurityCurrentParticipant::class)->setPublic(true);

        if (null === $config['directory']) {
            $container->register(IdentifierDirectory::class, IdentifierDirectory::class);
        }

        $container->setAlias(ParticipantDirectory::class, $config['directory'] ?? IdentifierDirectory::class);

        if (null === $config['contacts']) {
            $container->register(NoContacts::class, NoContacts::class);
        }

        $container->setAlias(ContactRelation::class, $config['contacts'] ?? NoContacts::class);
    }

    /**
     * @param Config        $config
     * @param array<string> $bundles
     */
    private static function notifications(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!$config['notifications']['enabled'] || !class_exists(Notifications::class) || !\in_array('PhilNotificationsBundle', $bundles, true)) {
            return;
        }

        $container->register(NotifyParticipants::class, NotifyParticipants::class)
            ->setArguments([new Reference(Notifications::class), $config['notifications']['type']])
            ->addTag('kernel.event_listener', ['event' => MessageSent::class]);
        $container->register(ConversationSubjects::class, ConversationSubjects::class)
            ->setArguments([
                new Reference(ConversationReader::class),
                new Reference(ParticipantDirectory::class),
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setAutoconfigured(true);
    }

    /**
     * @param Config $config
     */
    private static function realtime(ContainerBuilder $container, array $config): void
    {
        if (!$config['realtime']['enabled']) {
            return;
        }

        if (!interface_exists(HubInterface::class)) {
            throw new LogicException('"phil_conversation.realtime" pushes the new messages through Mercure, which is not installed: run "composer require symfony/mercure-bundle", or disable the option.');
        }

        $container->register(MercureMessagePublisher::class, MercureMessagePublisher::class)
            ->setArguments([new Reference($config['realtime']['hub']), $config['realtime']['topic_prefix'], new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('kernel.event_listener', ['event' => MessageSent::class]);
    }

    /**
     * @param Config $config
     */
    private static function pages(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(Environment::class) || !interface_exists(UrlGeneratorInterface::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(ConversationExtension::class, ConversationExtension::class)
            ->setArguments([new Reference(KitComponents::class), new Reference(ParticipantDirectory::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $container->register(ConversationRequest::class, ConversationRequest::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(ConversationController::class, ConversationController::class)
            ->setArguments([
                new Reference(Conversations::class),
                new Reference(Reception::class),
                new Reference(CurrentParticipant::class),
                new Reference(ConversationRequest::class),
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
                $config['realtime']['enabled'] ? $config['realtime']['topic_prefix'] : null,
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }
}
