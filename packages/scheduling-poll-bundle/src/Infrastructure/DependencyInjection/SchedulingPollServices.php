<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\DependencyInjection;

use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\TokenHasher;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\SchedulingPollBundle\Application\Contract\RespondentAddresses;
use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Application\Message\CloseDuePolls;
use Phil\SchedulingPollBundle\Application\Message\EraseRespondents;
use Phil\SchedulingPollBundle\Application\Message\SendChosenDate;
use Phil\SchedulingPollBundle\Application\NoRespondentAddresses;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineSchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\InvitationSchemaListener;
use Phil\SchedulingPollBundle\Infrastructure\Http\AnswerController;
use Phil\SchedulingPollBundle\Infrastructure\Mailer\ChosenDateMail;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemoryRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemorySchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\AnnounceChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\CloseDuePollsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\EraseRespondentsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\SendChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Privacy\SchedulingPollPrivacy;
use Phil\SchedulingPollBundle\Infrastructure\Scheduler\SchedulingPollSchedule;
use Phil\SchedulingPollBundle\Infrastructure\Twig\SchedulingPollTwig;
use Phil\Url\Url;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Scheduler\Schedule;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the stores, the links of `phil/access-links` under the keys and page of the configuration,
 * the polls and their views; the handlers of the messages when Messenger is there, the mail of the date chosen when the
 * Mailer is, the schedule when the Scheduler and the Lock are, the functions of the templates and the pages of a
 * respondent when Twig is, and the export and erasure when `phil/privacy-bundle` is.
 *
 * @phpstan-type Config array{store: string, entity_manager: string, connection: string, links: array{keys: list<string>, page: string, parameter: string}, link_lifetime: string, max_respondents: int, closing: array{frequency: string, batch: int}, erasure: array{frequency: string}, schedule: array{cache: string, lock_factory: string}, mailer: array{from: ?string}, respondent_addresses: ?string, privacy: array{account_kind: string}, kit_subdirectory: ?string}
 */
final class SchedulingPollServices
{
    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::stores($container, $config);

        $container->register(TokenHasher::class, TokenHasher::class)->setArguments($config['links']['keys']);
        $container->register(LinkFormat::class, LinkFormat::class)->setArguments([
            new Definition(Url::class, [$config['links']['page']])->setFactory([Url::class, 'fromString']),
            $config['links']['parameter'],
        ]);
        $container->register('phil_scheduling_poll.access_links', AccessLinks::class)->setArguments([
            new Reference('phil_scheduling_poll.invitation_store'),
            new Reference(TokenHasher::class),
            new Reference(LinkFormat::class),
            new Reference('clock'),
        ]);
        $container->register(AvailabilityCount::class, AvailabilityCount::class);
        $container->register(SchedulingPolls::class, SchedulingPolls::class)
            ->setArguments([
                new Reference(SchedulingPollRepository::class),
                new Reference(RespondentRepository::class),
                new Reference('phil_scheduling_poll.access_links'),
                new Reference('clock'),
                new Reference(AvailabilityCount::class),
                interface_exists(MessageBusInterface::class) ? new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['link_lifetime'],
                $config['max_respondents'],
            ])
            ->setPublic(true);
        $container->register(SchedulingPollViews::class, SchedulingPollViews::class)
            ->setArguments([new Reference(SchedulingPolls::class)])
            ->setPublic(true);

        if (null === $config['respondent_addresses']) {
            $container->register(NoRespondentAddresses::class, NoRespondentAddresses::class);
            $container->setAlias(RespondentAddresses::class, NoRespondentAddresses::class);
        } else {
            $container->setAlias(RespondentAddresses::class, $config['respondent_addresses']);
        }

        self::messages($container, $config);
        self::pages($container, $config);

        if (interface_exists(PersonalDataExporter::class)) {
            $container->register(SchedulingPollPrivacy::class, SchedulingPollPrivacy::class)
                ->setArguments([new Reference(RespondentRepository::class), new Reference(SchedulingPollRepository::class), $config['privacy']['account_kind']])
                ->setAutoconfigured(true);
        }
    }

    /**
     * @param Config $config
     */
    private static function stores(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemorySchedulingPollRepository::class, InMemorySchedulingPollRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->register(InMemoryRespondentRepository::class, InMemoryRespondentRepository::class)
                ->setArguments([new Reference(InMemorySchedulingPollRepository::class)])
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->register('phil_scheduling_poll.invitation_store', InMemoryInvitationStore::class);
            $container->setAlias(SchedulingPollRepository::class, InMemorySchedulingPollRepository::class)->setPublic(true);
            $container->setAlias(RespondentRepository::class, InMemoryRespondentRepository::class)->setPublic(true);

            return;
        }

        $entityManager = new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager');
        $container->register(DoctrineSchedulingPollRepository::class, DoctrineSchedulingPollRepository::class)->setArguments([$entityManager]);
        $container->register(DoctrineRespondentRepository::class, DoctrineRespondentRepository::class)->setArguments([$entityManager]);
        $container->register('phil_scheduling_poll.invitation_store', DbalInvitationStore::class)
            ->setArguments([new Reference('doctrine.dbal.'.$config['connection'].'_connection')]);
        $container->register(InvitationSchemaListener::class, InvitationSchemaListener::class)
            ->setArguments([new Reference('phil_scheduling_poll.invitation_store')])
            ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
        $container->setAlias(SchedulingPollRepository::class, DoctrineSchedulingPollRepository::class)->setPublic(true);
        $container->setAlias(RespondentRepository::class, DoctrineRespondentRepository::class)->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function messages(ContainerBuilder $container, array $config): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            return;
        }

        $handler = static fn (string $class, array $arguments, string $handles): Definition => $container->register($class, $class)->setArguments($arguments)->addTag('messenger.message_handler', ['handles' => $handles]);
        $handler(AnnounceChosenDateHandler::class, [new Reference(SchedulingPollRepository::class), new Reference(RespondentRepository::class), new Reference('messenger.default_bus')], AnnounceChosenDate::class);
        $handler(CloseDuePollsHandler::class, [new Reference(SchedulingPolls::class), $config['closing']['batch']], CloseDuePolls::class);
        $handler(EraseRespondentsHandler::class, [new Reference(SchedulingPolls::class)], EraseRespondents::class);

        if (interface_exists(MailerInterface::class)) {
            $container->register(ChosenDateMail::class, ChosenDateMail::class)->setArguments([
                new Reference('translator'),
                null === $config['mailer']['from'] ? null : new Definition(Address::class, [$config['mailer']['from']])->setFactory([Address::class, 'create']),
                '%kernel.default_locale%',
            ]);
            $handler(SendChosenDateHandler::class, [
                new Reference(SchedulingPollRepository::class),
                new Reference(RespondentRepository::class),
                new Reference(RespondentAddresses::class),
                new Reference(ChosenDateMail::class),
                new Reference('clock'),
                new Reference(MailerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ], SendChosenDate::class);
        }

        if (class_exists(Schedule::class)) {
            $container->register(SchedulingPollSchedule::class, SchedulingPollSchedule::class)
                ->setArguments([new Reference($config['schedule']['cache']), new Reference($config['schedule']['lock_factory'], ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['closing']['frequency'], $config['erasure']['frequency']])
                ->addTag('scheduler.schedule_provider', ['name' => SchedulingPollSchedule::NAME]);
        }
    }

    /**
     * @param Config $config
     */
    private static function pages(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(AsTwigFunction::class)) {
            return;
        }

        $container->register(SchedulingPollTwig::class, SchedulingPollTwig::class)
            // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
            ->setArguments([new Reference(SchedulingPollViews::class), $config['kit_subdirectory']])
            ->setAutoconfigured(true);

        if (class_exists(Request::class)) {
            $container->register(AnswerController::class, AnswerController::class)
                ->setArguments([
                    new Reference(SchedulingPolls::class),
                    new Reference(SchedulingPollViews::class),
                    new Reference(LinkFormat::class),
                    new Reference('twig'),
                    new Reference('translator'),
                    new Reference('clock'),
                    new Reference('security.csrf.token_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }
    }
}
