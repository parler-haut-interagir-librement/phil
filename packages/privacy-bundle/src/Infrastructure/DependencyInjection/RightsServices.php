<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\ConfiguredRetentionSchedule;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;
use Phil\PrivacyBundle\Application\Contract\ExportReadme;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Handler\ProcessDueAccountDeletionsHandler;
use Phil\PrivacyBundle\Application\Handler\RunRetentionHandler;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;
use Phil\PrivacyBundle\Application\Message\ProcessDueAccountDeletions;
use Phil\PrivacyBundle\Application\Message\RunRetention;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Infrastructure\Console\DisabledRightsWarning;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessDeletionsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\RunRetentionCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use Phil\PrivacyBundle\Infrastructure\Filesystem\EncryptedExportArchive;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentVaryListener;
use Phil\PrivacyBundle\Infrastructure\Http\ErasureProgressController;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Log\LoggedConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Routing\MountingRouteLoader;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use Phil\PrivacyBundle\Infrastructure\Security\SecurityCurrentDataSubject;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigExportReadme;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Workflow\StateMachine;

/**
 * The services of the rights of a member, the consent to cookies and the retention job. Durations, categories and
 * retention rules are checked here, so that a bad one fails the container build rather than a request.
 *
 * @phpstan-import-type RuleConfig from ConfiguredRetentionSchedule
 * @phpstan-import-type WorkflowConfig from ErasureWorkflowServices
 *
 * @phpstan-type ConsentConfig array{version: string, cookie_name: string, lifetime: string, proofs: string|null, categories: array<array-key, array{necessary: bool, labels: array<string, string>, descriptions: array<string, string>}>}
 * @phpstan-type LimitConfig array{limit: int, interval: string}
 * @phpstan-type GuestConfig array{enabled: bool, link_lifetime: string, from: string|null, sender: string|null, subjects: string|null, store: string|null, cache: string, per_client: LimitConfig, per_address: LimitConfig}
 * @phpstan-type Config array{
 *     retention_schedule_source: string|null,
 *     retention: array{frequency: string, rules: array<array-key, RuleConfig>},
 *     account_deletion: array{delay: string, frequency: string, store: string|null, export_archive: string|null, export_directory: string, export_retention: string},
 *     consent: ConsentConfig,
 *     cookies: array{inventory: string|null, watch: bool|null},
 *     guest_requests: GuestConfig,
 *     erasure_workflow: WorkflowConfig,
 *     incidents: array{store: string|null},
 *     email_tracking: array{enabled: bool, version: string, store: string|null},
 *     rights: array{export: array{disable: scalar|null}, erasure: array{disable: scalar|null}},
 *     pages: array{auto_mount: bool, prefix: string, layout: string, current_subject: string|null, clock: string},
 * }
 */
final class RightsServices
{
    public const string PURGER_TAG = 'phil_privacy.retention_purger';

    /**
     * The tag of every module's exporter, `PhilPrivacyBundle::EXPORTER_TAG`.
     */
    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    /**
     * The tag of every module's eraser, `PhilPrivacyBundle::ERASER_TAG`.
     */
    public const string ERASER_TAG = 'phil_privacy.eraser';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function load(array $config, ServicesConfigurator $services, ContainerBuilder $builder, Reference $connection): ?Reference
    {
        $builder->registerForAutoconfiguration(RetentionPurger::class)->addTag(self::PURGER_TAG);
        $clock = new Reference($config['pages']['clock']);

        self::database($services, $connection, $config['incidents']['store'], $clock);
        self::emailTracking($config['email_tracking'], $services, $clock);
        $rights = self::rights($config['rights'], $services);
        self::export($services);
        self::deletions($config['account_deletion'], $services, $clock);
        self::consent($config['consent'], $services, $builder, $clock);
        CookieServices::load($config['consent'], $config['cookies'], $services, $builder);
        GuestRequestServices::load($config['guest_requests'], $services, $clock);
        ErasureWorkflowServices::load($config['erasure_workflow'], $services, $builder, $connection, $clock);
        self::pages($config['pages'], $services);
        $source = self::retention($config, $services, $clock);
        self::schedule($config, $services, $source instanceof Reference);
        self::warning($rights, $services);

        return $source;
    }

    private static function database(ServicesConfigurator $services, Reference $connection, ?string $incidents, Reference $clock): void
    {
        $services->set(DbalAccountDeletions::class)->args([$connection])->tag(self::PURGER_TAG);
        $services->set(DbalConsentProofs::class)->args([$connection])->tag(self::PURGER_TAG);
        $services->set(DbalGuestRequests::class)->args([$connection, '%kernel.secret%']);
        $services->set(DbalIncidentRegister::class)->args([$connection, new Reference((string) $clock, ContainerInterface::NULL_ON_INVALID_REFERENCE)])->tag(self::PURGER_TAG);
        $services->set(DbalEmailTrackingConsents::class)->args([$connection]);
        $services->set(PrivacySchemaListener::class)->args([$connection])->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
        $services->alias(IncidentRegister::class, $incidents ?? DbalIncidentRegister::class)->public();

        if (class_exists(Command::class)) {
            $services->set(IncidentCommand::class)->args([new Reference(IncidentRegister::class), new Reference('clock')])->tag('console.command');
            $services->set(IncidentsCommand::class)->args([new Reference(IncidentRegister::class)])->tag('console.command');
        }
    }

    /**
     * Each member's consent to tracked emails, part of their export and of their erasure. The version of the text is
     * checked here, so that a bad one fails the build.
     *
     * @param array{enabled: bool, version: string, store: string|null} $config
     */
    private static function emailTracking(array $config, ServicesConfigurator $services, Reference $clock): void
    {
        if (!$config['enabled']) {
            return;
        }

        if (1 !== preg_match(ConsentPolicy::VERSION_PATTERN, $config['version'])) {
            throw new InvalidConfigurationException('phil_privacy.email_tracking.version: letters, digits, ".", "_" and "-", 32 at most, starting with a letter or a digit.');
        }

        $services->alias(EmailTrackingStore::class, $config['store'] ?? DbalEmailTrackingConsents::class);
        $services->set(EmailTrackingConsents::class)
            ->args([new Reference(EmailTrackingStore::class), $clock, $config['version']])
            ->tag(self::EXPORTER_TAG)
            ->tag(self::ERASER_TAG)
            ->public();
    }

    /**
     * The archive of an access answer: its README rendered by Twig in the person's language, or in plain English.
     */
    private static function export(ServicesConfigurator $services): void
    {
        $services->set(PlainExportReadme::class);
        $services->set(TwigExportReadme::class)->args([new Reference('twig')]);
        $services->alias(ExportReadme::class, TwigExportReadme::class);
        $services->set(ExportPackage::class)->args([new Reference(ExportReadme::class), new Reference('clock')])->public();
    }

    /**
     * @param array{export: array{disable: scalar|null}, erasure: array{disable: scalar|null}} $rights
     */
    private static function rights(array $rights, ServicesConfigurator $services): PrivacyRights
    {
        $parsed = new PrivacyRights(
            self::enabled('export', $rights['export']['disable'], PrivacyRights::DISABLE_EXPORT_SENTENCE),
            self::enabled('erasure', $rights['erasure']['disable'], PrivacyRights::DISABLE_ERASURE_SENTENCE),
        );

        $services->set(PrivacyRights::class)->args([$parsed->export, $parsed->erasure])->public();

        return $parsed;
    }

    private static function enabled(string $right, bool|float|int|string|null $sentence, string $expected): bool
    {
        if (null === $sentence) {
            return true;
        }

        if ($sentence !== $expected) {
            throw new InvalidConfigurationException(\sprintf('phil_privacy.rights.%s.disable must be exactly this sentence: "%s".', $right, $expected));
        }

        return false;
    }

    /**
     * @param array{delay: string, frequency: string, store: string|null, export_archive: string|null, export_directory: string, export_retention: string} $config
     */
    private static function deletions(array $config, ServicesConfigurator $services, Reference $clock): void
    {
        self::duration('account_deletion.delay', $config['delay']);
        self::duration('account_deletion.export_retention', $config['export_retention']);

        $services->alias(AccountDeletionStore::class, $config['store'] ?? DbalAccountDeletions::class);

        if (null === $config['export_archive']) {
            $services->set(EncryptedExportArchive::class)->args([$config['export_directory'], '%kernel.secret%']);
        }

        $services->alias(ExportArchive::class, $config['export_archive'] ?? EncryptedExportArchive::class);

        $services->set(DeletionWorkflow::SERVICE)
            ->class(StateMachine::class)
            ->factory(DeletionWorkflow::create(...))
            ->args([new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->tag('workflow', ['name' => DeletionWorkflow::NAME, 'supports' => [AccountDeletion::class]])
            ->public();

        $services->set(AccountDeletions::class)->args([
            new Reference(AccountDeletionStore::class),
            new Reference(DeletionWorkflow::SERVICE),
            new Reference('lock.factory'),
            new Reference(SubjectExport::class),
            new Reference(SubjectErasure::class),
            new Reference(ExportArchive::class),
            $clock,
            $config['delay'],
            $config['export_retention'],
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(ExportPackage::class),
        ])->public();

        $services->set(PersonalDataDownload::class)->args([new Reference(SubjectExport::class), new Reference('lock.factory'), new Reference(ExportPackage::class)]);
        $services->set(AdvanceAccountDeletionHandler::class)
            ->args([new Reference(AccountDeletions::class)])
            ->tag('messenger.message_handler', ['handles' => AdvanceAccountDeletion::class]);
        $services->set(ProcessDueAccountDeletionsHandler::class)
            ->args([new Reference(AccountDeletions::class), new Reference('messenger.default_bus')])
            ->tag('messenger.message_handler', ['handles' => ProcessDueAccountDeletions::class]);

        if (class_exists(Command::class)) {
            $services->set(ProcessDeletionsCommand::class)->args([new Reference(AccountDeletions::class)])->tag('console.command');
        }
    }

    /**
     * @param ConsentConfig $config
     */
    private static function consent(array $config, ServicesConfigurator $services, ContainerBuilder $builder, Reference $clock): void
    {
        self::duration('consent.lifetime', $config['lifetime']);

        $services->set(LoggedConsentProofs::class)->args([new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $builder->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, $config['proofs']);
        $services->alias(ConsentProofs::class, $config['proofs'] ?? DbalConsentProofs::class);
        $services->set(ConsentRecorder::class)->args([new Reference(ConsentPolicy::class), new Reference(ConsentProofs::class), $clock]);
        $services->set(ConsentToken::class)->args(['%kernel.secret%']);
        $services->set(ConsentCookie::class)->args([new Reference(ConsentToken::class), $clock, $config['cookie_name'], $config['lifetime']]);
        $services->set(ConsentContext::class)->args([new Reference('request_stack'), new Reference(ConsentCookie::class), new Reference(ConsentPolicy::class)])->public();
        $services->set(ConsentVaryListener::class)->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
    }

    /**
     * @param array{auto_mount: bool, prefix: string, layout: string, current_subject: string|null, clock: string} $config
     */
    private static function pages(array $config, ServicesConfigurator $services): void
    {
        $clock = new Reference($config['clock']);
        $services->set(PrivacyPages::class)->args([new Reference('twig'), new Reference('security.csrf.token_manager'), new Reference('router'), $config['layout']]);
        $services->set(ConsentController::class)
            ->args([new Reference(PrivacyPages::class), new Reference(ConsentContext::class), new Reference(ConsentCookie::class), new Reference(ConsentRecorder::class), new Reference(ConsentPolicy::class), new Reference(CookieInventory::class)])
            ->public()
            ->tag('controller.service_arguments');
        $services->set(ConsentExtension::class)
            ->args([new Reference(ConsentContext::class), new Reference(PrivacyPages::class), new Reference('request_stack'), new Reference(CookieInventory::class), new Reference(ConsentPolicy::class)])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');
        $services->set(EmbedExtension::class)
            ->args([new Reference(ConsentContext::class), new Reference(ConsentPolicy::class), new Reference(PrivacyPages::class), new Reference('request_stack')])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');
        $services->set(ErasureProgressController::class)
            ->args([
                new Reference(PrivacyPages::class),
                new Reference(ErasureRequests::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(CurrentDataSubject::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '%'.ErasureWorkflowServices::OPERATOR_ROLE.'%',
            ])
            ->public()
            ->tag('controller.service_arguments');
        $services->set(GuestRequestController::class)
            ->args([new Reference(PrivacyPages::class), new Reference(GuestRequests::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->public()
            ->tag('controller.service_arguments');

        $services->set(SecurityCurrentDataSubject::class)->args([new Reference('security.token_storage')]);
        $services->alias(CurrentDataSubject::class, $config['current_subject'] ?? SecurityCurrentDataSubject::class);
        $services->set(PreferencesController::class)
            ->args([
                new Reference(PrivacyPages::class),
                new Reference(CurrentDataSubject::class),
                new Reference(PrivacyRights::class),
                $clock,
                new Reference(AccountDeletions::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(PersonalDataDownload::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(EmailTrackingConsents::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public()
            ->tag('controller.service_arguments');

        if ($config['auto_mount']) {
            $services->set(MountingRouteLoader::class)
                ->decorate('routing.loader', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->args([new Reference('.inner'), $config['prefix'], true]);
        }
    }

    /**
     * @param Config $config
     */
    private static function retention(array $config, ServicesConfigurator $services, Reference $clock): ?Reference
    {
        $named = $config['retention_schedule_source'];
        $rules = $config['retention']['rules'];

        if (null !== $named && [] !== $rules) {
            throw new InvalidConfigurationException('phil_privacy: name retention_schedule_source or write retention.rules, not both: a schedule has one authority.');
        }

        if ([] !== $rules) {
            try {
                $schedule = new ConfiguredRetentionSchedule($rules)->load();
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                throw new InvalidConfigurationException('phil_privacy.retention.rules: '.$invalidPrivacyValue->getMessage(), $invalidPrivacyValue->getCode(), previous: $invalidPrivacyValue);
            }

            self::incidentFloor($schedule);

            $services->set(ConfiguredRetentionSchedule::class)->args([$rules]);
            $named = ConfiguredRetentionSchedule::class;
        }

        if (null === $named) {
            return null;
        }

        $source = new Reference($named);
        $services->set(RetentionRun::class)->args([$source, new TaggedIteratorArgument(self::PURGER_TAG)])->public();
        $services->set(RetentionJob::class)
            ->args([new Reference(RetentionRun::class), new Reference('lock.factory'), $clock, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->public();
        $services->set(RunRetentionHandler::class)->args([new Reference(RetentionJob::class)])->tag('messenger.message_handler', ['handles' => RunRetention::class]);

        if (class_exists(Command::class)) {
            $services->set(RunRetentionCommand::class)->args([new Reference(RetentionJob::class)])->tag('console.command');
        }

        return $source;
    }

    /**
     * Section 8 of the Québec regulation keeps the register of incidents at least five years after the discovery: a rule
     * on its table that would purge sooner fails the build. The purge itself never goes below that floor either.
     */
    private static function incidentFloor(RetentionSchedule $schedule): void
    {
        $minimum = RetentionPeriod::fixed(Incident::MINIMUM_RETENTION);

        foreach ($schedule->rules() as $rule) {
            if (PrivacyTables::INCIDENTS === $rule->store && !$rule->period->lastsAtLeast($minimum)) {
                throw new InvalidConfigurationException(\sprintf('phil_privacy.retention.rules.%s: the register of confidentiality incidents is kept at least five years after the discovery (section 8 of the Regulation respecting confidentiality incidents, chapter A-2.1, r. 3.1); "%s" is shorter than %s.', $rule->id, $rule->period->duration ?? '', Incident::MINIMUM_RETENTION));
            }
        }
    }

    /**
     * @param Config $config
     */
    private static function schedule(array $config, ServicesConfigurator $services, bool $retention): void
    {
        if (!interface_exists(ScheduleProviderInterface::class)) {
            return;
        }

        $services->set(PrivacySchedule::class)
            ->args([$config['account_deletion']['frequency'], $retention ? $config['retention']['frequency'] : null])
            ->tag('scheduler.schedule_provider', ['name' => PrivacySchedule::NAME]);
    }

    private static function warning(PrivacyRights $rights, ServicesConfigurator $services): void
    {
        if ([] !== $rights->warnings()) {
            $services->set(DisabledRightsWarning::class)
                ->args([new Reference(PrivacyRights::class)])
                ->tag('kernel.event_listener', ['event' => 'console.command']);
        }
    }

    public static function duration(string $key, string $duration): void
    {
        try {
            RetentionPeriod::fixed($duration);
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            throw new InvalidConfigurationException(\sprintf('phil_privacy.%s: %s', $key, $invalidPrivacyValue->getMessage()), $invalidPrivacyValue->getCode(), previous: $invalidPrivacyValue);
        }
    }
}
