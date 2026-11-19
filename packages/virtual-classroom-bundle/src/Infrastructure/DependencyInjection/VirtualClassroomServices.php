<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\BigBlueButton\ChecksumAlgorithm;
use Phil\VirtualClassroom\Http\HttpBudget;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\CallbackUrls;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Application\Contract\CurrentParticipant;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Event\AttendanceUpdated;
use Phil\VirtualClassroomBundle\Infrastructure\Console\SyncCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Console\WebhookUrlCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\CoursesCompletion;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\VirtualClassroomActivity;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Doctrine\ClassroomSchemaListener;
use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoom;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoomHandler;
use Phil\VirtualClassroomBundle\Infrastructure\Privacy\ClassroomPrivacy;
use Phil\VirtualClassroomBundle\Infrastructure\Routing\RoutedCallbackUrls;
use Phil\VirtualClassroomBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Provider array{type: string, service: ?string, url: ?string, secret: ?string, checksum: string, analytics: bool, signed_analytics: bool, app_id: ?string, key_id: ?string, private_key: ?string, webhook_secret: ?string, token_ttl: int, audience: string, subject: ?string, timeout: float, max_duration: float, max_response_bytes: int}
 * @phpstan-type Config array{default_provider: ?string, providers: array<string, Provider>, store: string, connection: string, table_prefix: non-empty-string, clock: string, http_client: string, webhook_secret: string, allow_localhost: bool, moderator_role: string, current_participant: ?string, courses: array{enabled: bool, threshold: int<1, 100>}, privacy: array{enabled: bool}}
 */
final readonly class VirtualClassroomServices
{
    public const string STORE_DBAL = 'dbal';

    public const string STORE_MEMORY = 'memory';

    public const string PROVIDER_PREFIX = 'phil_virtual_classroom.provider.';

    private function __construct()
    {
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles the names of the enabled bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        self::store($container, $config);
        $providers = self::providers($container, $config);

        $container->register(WebhookSigner::class, WebhookSigner::class)->setArguments([$config['webhook_secret']]);
        $container->register(RoutedCallbackUrls::class, RoutedCallbackUrls::class)
            ->setArguments([new Reference('router'), new Reference(WebhookSigner::class), $config['allow_localhost']]);
        $container->setAlias(CallbackUrls::class, RoutedCallbackUrls::class);
        $container->register(Providers::class, Providers::class)
            ->setArguments([ServiceLocatorTagPass::register($container, $providers), array_keys($providers), $config['default_provider'] ?? array_key_first($providers)])
            ->setPublic(true);
        $container->register(Classrooms::class, Classrooms::class)
            ->setArguments([
                new Reference(ClassroomStore::class),
                new Reference(Providers::class),
                new Reference(CallbackUrls::class),
                new Reference($config['clock']),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        self::participant($container, $config);
        $container->register(JoinController::class, JoinController::class)
            ->setArguments([new Reference(Classrooms::class), new Reference(CurrentParticipant::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(WebhookController::class, WebhookController::class)
            ->setArguments([new Reference(Classrooms::class), new Reference(WebhookSigner::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        self::console($container);
        self::messenger($container);
        self::courses($container, $config, $bundles);
        self::privacy($container, $config, $bundles);
    }

    /**
     * @param Config $config
     */
    private static function store(ContainerBuilder $container, array $config): void
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryClassroomStore::class, InMemoryClassroomStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(ClassroomStore::class, InMemoryClassroomStore::class)->setPublic(true);

            return;
        }

        if (self::STORE_DBAL !== $config['store']) {
            $container->setAlias(ClassroomStore::class, $config['store'])->setPublic(true);

            return;
        }

        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $container->register(DbalClassroomStore::class, DbalClassroomStore::class)->setArguments([$connection, $config['table_prefix']]);
        $container->setAlias(ClassroomStore::class, DbalClassroomStore::class)->setPublic(true);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(ClassroomSchemaListener::class, ClassroomSchemaListener::class)
                ->setArguments([$connection, $config['table_prefix']])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }
    }

    /**
     * @param Config $config
     *
     * @return array<string, Reference> the service of each provider, by name
     */
    private static function providers(ContainerBuilder $container, array $config): array
    {
        $references = [];

        foreach ($config['providers'] as $name => $provider) {
            $id = self::PROVIDER_PREFIX.$name;

            if ('service' === $provider['type']) {
                $container->setAlias($id, (string) $provider['service']);
            } else {
                $container->setDefinition($id, self::provider($provider, $config));
            }

            $references[$name] = new Reference($id);
        }

        return $references;
    }

    /**
     * @param Provider $provider
     * @param Config   $config
     */
    private static function provider(array $provider, array $config): Definition
    {
        $clock = new Reference($config['clock']);
        $url = null === $provider['url'] ? null : new Definition(Url::class)->setFactory([Url::class, 'fromString'])->setArguments([$provider['url']]);

        return match ($provider['type']) {
            'bigbluebutton' => new Definition(BigBlueButton::class)->setArguments([
                new Reference($config['http_client']),
                $url,
                $provider['secret'],
                $clock,
                new Definition(ChecksumAlgorithm::class)->setFactory([ChecksumAlgorithm::class, 'from'])->setArguments([$provider['checksum']]),
                new Definition(HttpBudget::class)->setArguments([$provider['timeout'], $provider['max_duration'], $provider['max_response_bytes']]),
                $provider['analytics'],
                $provider['signed_analytics'],
            ]),
            'jaas' => new Definition(Jitsi::class)->setFactory([Jitsi::class, 'jaas'])->setArguments([
                $provider['app_id'],
                $provider['key_id'],
                $provider['private_key'],
                $clock,
                $provider['token_ttl'],
                $provider['webhook_secret'],
                $url,
            ]),
            default => new Definition(Jitsi::class)->setFactory([Jitsi::class, 'selfHosted'])->setArguments([
                $url,
                $provider['app_id'],
                $provider['secret'],
                $clock,
                $provider['token_ttl'],
                $provider['webhook_secret'],
                $provider['audience'],
                $provider['subject'],
            ]),
        };
    }

    /**
     * @param Config $config
     */
    private static function participant(ContainerBuilder $container, array $config): void
    {
        if (null !== $config['current_participant']) {
            $container->setAlias(CurrentParticipant::class, $config['current_participant'])->setPublic(true);

            return;
        }

        if (interface_exists(AuthorizationCheckerInterface::class)) {
            $container->register(SecurityCurrentParticipant::class, SecurityCurrentParticipant::class)
                ->setArguments([new Reference('security.token_storage'), new Reference('security.authorization_checker'), $config['moderator_role']]);
            $container->setAlias(CurrentParticipant::class, SecurityCurrentParticipant::class)->setPublic(true);
        }
    }

    private static function console(ContainerBuilder $container): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        $container->register(SyncCommand::class, SyncCommand::class)
            ->setArguments([new Reference(Classrooms::class), new Reference(ClassroomStore::class)])
            ->addTag('console.command');
        $container->register(WebhookUrlCommand::class, WebhookUrlCommand::class)
            ->setArguments([new Reference(Providers::class), new Reference(WebhookSigner::class), new Reference('router')])
            ->addTag('console.command');
    }

    private static function messenger(ContainerBuilder $container): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            return;
        }

        $container->register(SyncRoomHandler::class, SyncRoomHandler::class)
            ->setArguments([new Reference(Classrooms::class)])
            ->addTag('messenger.message_handler', ['handles' => SyncRoom::class]);
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles
     */
    private static function courses(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!$config['courses']['enabled'] || !interface_exists(ActivityType::class) || !\in_array('PhilCoursesBundle', $bundles, true)) {
            return;
        }

        $container->register(VirtualClassroomActivity::class, VirtualClassroomActivity::class)
            ->setArguments([new Reference(Classrooms::class), new Reference(Providers::class), new Reference('router')])
            ->addTag(ActivityType::TAG)
            ->setPublic(true);
        $container->register(CoursesCompletion::class, CoursesCompletion::class)
            ->setArguments([new Reference(Classrooms::class), new Reference(Courses::class), new Reference(Enrollments::class), $config['courses']['threshold']])
            ->addTag('kernel.event_listener', ['event' => AttendanceUpdated::class])
            ->setPublic(true);
    }

    /**
     * @param Config       $config
     * @param list<string> $bundles
     */
    private static function privacy(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!$config['privacy']['enabled'] || !interface_exists(PersonalDataEraser::class) || !\in_array('PhilPrivacyBundle', $bundles, true)) {
            return;
        }

        $container->register(ClassroomPrivacy::class, ClassroomPrivacy::class)
            ->setArguments([new Reference(ClassroomStore::class), $config['table_prefix']])
            ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
            ->addTag(PhilPrivacyBundle::ERASER_TAG)
            ->setPublic(true);
    }
}
