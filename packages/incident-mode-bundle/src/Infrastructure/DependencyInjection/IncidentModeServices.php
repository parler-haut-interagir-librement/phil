<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Contract\EstablishedMembers;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentSchemaListener;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncident;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoEstablishedMembers;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Phil\IncidentModeBundle\Application\DurationInput;
use Phil\IncidentModeBundle\Infrastructure\Audit\AuditIncidents;
use Phil\IncidentModeBundle\Infrastructure\Console\ExtendIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\LiftIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\ListIncidentsCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\StartIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Form\SuspendedDuringTypeExtension;
use Phil\IncidentModeBundle\Infrastructure\Http\AdminRequest;
use Phil\IncidentModeBundle\Infrastructure\Http\CheckpointListener;
use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use Phil\IncidentModeBundle\Infrastructure\Http\SuspensionPage;
use Phil\IncidentModeBundle\Infrastructure\Privacy\IncidentPrivacy;
use Phil\IncidentModeBundle\Infrastructure\Scheduler\IncidentSchedule;
use Phil\IncidentModeBundle\Infrastructure\Security\SecurityCurrentMember;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Infrastructure\Twig\IncidentModeExtension;
use Phil\IncidentModeBundle\Infrastructure\Twig\KitComponents;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Schedule;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;

/**
 * The services of the bundle, from its configuration: the store and its schema listener on the configured connection,
 * the presets, `IncidentMode` with the clock and the event dispatcher of the application, the restrictions with their
 * established members, the checkpoints, the words, the commands, the pages and their routes' controllers, the lifting
 * by Messenger and Scheduler, the audit and the privacy — each only when its component or bundle is there.
 *
 * @phpstan-type PresetConfig array{label: string, restrictions: list<string>, proposed: string, longest: string}
 * @phpstan-type Config array{
 *     connection: string,
 *     built_in_presets: bool,
 *     without_presets: list<string>,
 *     spare_established: list<string>,
 *     established_members: ?string,
 *     current_member: ?string,
 *     layout: string,
 *     kit_subdirectory: ?string,
 *     timezone: ?string,
 *     presets: array<array-key, PresetConfig>,
 *     checkpoints: array{status: int},
 *     admin: array{enabled: bool, role: string, page_size: positive-int},
 *     lifting: array{messenger: bool, bus: string, schedule: array{enabled: bool, name: string, frequency: string}},
 *     audit: array{enabled: bool, segment: string},
 *     privacy: bool,
 * }
 */
final class IncidentModeServices
{
    public const string DEFAULT_LAYOUT = '@PhilIncidentMode/layout.html.twig';

    public const string INTL_EXTENSION = 'phil_incident_mode.twig.intl_extension';

    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    /**
     * The parameter that holds the role of the administration, set only when the administration is on: what another
     * bundle reads to offer the page of the incidents to that role alone.
     */
    public const string ADMIN_ROLE_PARAMETER = 'phil_incident_mode.admin.role';

    /**
     * The name of the schedule of the recurring sweep, kept here rather than on `IncidentSchedule`, whose interface
     * belongs to `symfony/scheduler`: the configuration reads it whether that component is installed or not.
     */
    public const string DEFAULT_SCHEDULE_NAME = 'incident_mode';

    /** How often the recurring sweep runs by default; see `DEFAULT_SCHEDULE_NAME`. */
    public const string DEFAULT_SCHEDULE_FREQUENCY = '5 minutes';

    /** The parameter that names the bus of the delayed messages, for IncidentModePass. */
    public const string BUS_PARAMETER = 'phil_incident_mode.lifting.bus';

    private function __construct()
    {
    }

    /**
     * @param Config          $config
     * @param list<array-key> $bundles the names of the registered bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $clock = new Reference('clock');

        $container->register(DbalActivationStore::class, DbalActivationStore::class)->setArguments([$connection]);
        $container->setAlias(ActivationStore::class, DbalActivationStore::class);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(IncidentSchemaListener::class, IncidentSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->setDefinition(Presets::class, self::presets($config));

        $container->register(NoLiftScheduler::class, NoLiftScheduler::class);
        $container->setAlias(LiftScheduler::class, NoLiftScheduler::class);
        self::lifting($container, $config['lifting'], $clock);

        $container->register(IncidentMode::class, IncidentMode::class)
            ->setArguments([new Reference(Presets::class), new Reference(ActivationStore::class), $clock, new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(LiftScheduler::class)]);

        $container->register(NoEstablishedMembers::class, NoEstablishedMembers::class);
        $container->setAlias(EstablishedMembers::class, $config['established_members'] ?? NoEstablishedMembers::class);
        $container->register(IncidentRestrictions::class, IncidentRestrictions::class)
            ->setArguments([new Reference(IncidentMode::class), new Reference(EstablishedMembers::class), self::restrictions($config['spare_established'])]);

        $container->register(SecurityCurrentMember::class, SecurityCurrentMember::class)
            ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->setAlias(CurrentMember::class, $config['current_member'] ?? SecurityCurrentMember::class);

        $container->register(Checkpoint::class, Checkpoint::class)
            ->setArguments([new Reference(IncidentMode::class), new Reference(IncidentRestrictions::class), new Reference(CurrentMember::class), $clock, $config['checkpoints']['status']]);
        $container->register(IncidentWording::class, IncidentWording::class)
            ->setArguments([new Reference('translator'), $config['timezone']]);

        self::commands($container);
        self::pages($container, $config);
        self::forms($container);
        self::audit($container, $config['audit'], $bundles);

        if ($config['privacy'] && interface_exists(PersonalDataEraser::class) && \in_array('PhilPrivacyBundle', $bundles, true)) {
            $container->register(IncidentPrivacy::class, IncidentPrivacy::class)
                ->setArguments([new Reference(ActivationStore::class)])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }
    }

    /**
     * The catalogue: the built-in presets, less those left out, then the application's, each checked now so that a
     * preset the library refuses fails the build of the container rather than a page.
     *
     * @param Config $config
     */
    private static function presets(array $config): Definition
    {
        $presets = [];

        foreach ($config['presets'] as $name => $preset) {
            try {
                $proposed = DurationInput::parse($preset['proposed']);
                $longest = DurationInput::parse($preset['longest']);
                Preset::of((string) $name, $preset['label'], $preset['restrictions'], $proposed, $longest);
            } catch (InvalidIncident $invalidIncident) {
                throw new InvalidConfigurationException(\sprintf('The preset "%s" of phil_incident_mode.presets is refused: %s', $name, $invalidIncident->getMessage()), $invalidIncident->getCode(), previous: $invalidIncident);
            }

            $presets[] = new Definition(Preset::class, [$name, $preset['label'], $preset['restrictions'], self::duration($proposed), self::duration($longest)])
                ->setFactory([Preset::class, 'of']);
        }

        if (!$config['built_in_presets']) {
            return new Definition(Presets::class, [$presets]);
        }

        return new Definition(Presets::class, [$presets, $config['without_presets']])->setFactory([Presets::class, 'withDefaults']);
    }

    private static function duration(Duration $duration): Definition
    {
        return new Definition(Duration::class, [$duration->seconds])->setFactory([Duration::class, 'ofSeconds']);
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function restrictions(array $names): array
    {
        try {
            return array_map(static fn (string $name): string => Restriction::named($name)->name, $names);
        } catch (InvalidIncident $invalidIncident) {
            throw new InvalidConfigurationException('phil_incident_mode.spare_established names restrictions: '.$invalidIncident->getMessage(), $invalidIncident->getCode(), previous: $invalidIncident);
        }
    }

    /**
     * The delayed message of each incident and the recurring sweep, when Messenger and Scheduler are installed; the
     * pass falls back on the sweep of the command when the bus is not there.
     *
     * @param array{messenger: bool, bus: string, schedule: array{enabled: bool, name: string, frequency: string}} $lifting
     */
    private static function lifting(ContainerBuilder $container, array $lifting, Reference $clock): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            return;
        }

        $container->setParameter(self::BUS_PARAMETER, $lifting['bus']);
        $container->register(LiftIncidentHandler::class, LiftIncidentHandler::class)
            ->setArguments([new Reference(IncidentMode::class)])
            ->addTag('messenger.message_handler', ['handles' => LiftIncident::class]);
        $container->register(LiftExpiredIncidentsHandler::class, LiftExpiredIncidentsHandler::class)
            ->setArguments([new Reference(IncidentMode::class)])
            ->addTag('messenger.message_handler', ['handles' => LiftExpiredIncidents::class]);

        if ($lifting['messenger']) {
            $container->register(MessengerLiftScheduler::class, MessengerLiftScheduler::class)->setArguments([new Reference($lifting['bus']), $clock]);
            $container->setAlias(LiftScheduler::class, MessengerLiftScheduler::class);
        }

        if ($lifting['schedule']['enabled'] && class_exists(Schedule::class)) {
            $container->register(IncidentSchedule::class, IncidentSchedule::class)
                ->setArguments([$lifting['schedule']['frequency']])
                ->addTag('scheduler.schedule_provider', ['name' => $lifting['schedule']['name']]);
        }
    }

    private static function commands(ContainerBuilder $container): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        foreach ([StartIncidentCommand::class, ExtendIncidentCommand::class, LiftIncidentCommand::class, ListIncidentsCommand::class] as $command) {
            $container->register($command, $command)->setArguments([new Reference(IncidentMode::class)])->addTag('console.command');
        }
    }

    /**
     * The checkpoints of the controllers and the routes, the public pages, the Twig functions and the administration.
     *
     * @param Config $config
     */
    private static function pages(ContainerBuilder $container, array $config): void
    {
        $twig = class_exists(Environment::class);

        $container->register(CheckpointListener::class, CheckpointListener::class)
            ->setArguments([new Reference(Checkpoint::class), $twig ? new Reference(SuspensionPage::class) : null])
            ->addTag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController'])
            ->addTag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onKernelException']);

        if (!$twig) {
            if ($config['admin']['enabled']) {
                throw new InvalidConfigurationException('phil_incident_mode.admin needs twig/twig: install it, or set "admin.enabled" to false.');
            }

            return;
        }

        $container->register(SuspensionPage::class, SuspensionPage::class)
            ->setArguments([new Reference(Checkpoint::class), new Reference('twig'), $config['layout']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        // The subdirectory of the Toolkit is read by IncidentModePass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(IncidentModeExtension::class, IncidentModeExtension::class)
            ->setArguments([new Reference(Checkpoint::class), new Reference(IncidentWording::class), new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        // format_datetime and the other filters of the dates; TwigExtraBundle registers them when present.
        if (!$container->hasExtension('twig_extra')) {
            $container->register(self::INTL_EXTENSION, IntlExtension::class)->addTag('twig.extension');
        }

        if (!$config['admin']['enabled']) {
            return;
        }

        $container->setParameter(self::ADMIN_ROLE_PARAMETER, $config['admin']['role']);
        $container->register(AdminRequest::class, AdminRequest::class)->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(IncidentAdminController::class, IncidentAdminController::class)
            ->setArguments([
                new Reference(IncidentMode::class),
                new Reference(CurrentMember::class),
                new Reference(AdminRequest::class),
                new Reference('twig'),
                new Reference('router'),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['admin']['role'],
                $config['layout'],
                $config['admin']['page_size'],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    private static function forms(ContainerBuilder $container): void
    {
        if (!interface_exists(FormTypeExtensionInterface::class)) {
            return;
        }

        $container->register(SuspendedDuringTypeExtension::class, SuspendedDuringTypeExtension::class)
            ->setArguments([new Reference(Checkpoint::class), new Reference(IncidentWording::class)])
            ->addTag('form.type_extension', ['extended_type' => FormType::class]);
    }

    /**
     * @param array{enabled: bool, segment: string} $audit
     * @param list<array-key>                       $bundles
     */
    private static function audit(ContainerBuilder $container, array $audit, array $bundles): void
    {
        if (!$audit['enabled'] || !class_exists(AuditTrail::class) || !\in_array('PhilAuditBundle', $bundles, true)) {
            return;
        }

        $container->register(AuditIncidents::class, AuditIncidents::class)
            ->setArguments([new Reference(AuditTrail::class), $audit['segment']])
            ->addTag('kernel.event_listener', ['event' => IncidentStarted::class, 'method' => 'started'])
            ->addTag('kernel.event_listener', ['event' => IncidentExtended::class, 'method' => 'extended'])
            ->addTag('kernel.event_listener', ['event' => IncidentLifted::class, 'method' => 'lifted']);
    }
}
