<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\LegalNotices\SiteIdentity;
use Phil\Outbox\Contract\Outbox;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\ExportReadme;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Handler\ProcessDueAccountDeletionsHandler;
use Phil\PrivacyBundle\Application\Handler\RunRetentionHandler;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentJournalCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentNoticeCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessDeletionsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\RunRetentionCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentVaryListener;
use Phil\PrivacyBundle\Infrastructure\Http\ErasureProgressController;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookieListener;
use Phil\PrivacyBundle\Infrastructure\LegalNotices\SiteIdentityOrganisation;
use Phil\PrivacyBundle\Infrastructure\Log\LoggedConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Mailer\MailerGuestLinkSender;
use Phil\PrivacyBundle\Infrastructure\Routing\MountingRouteLoader;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use Phil\PrivacyBundle\Infrastructure\Security\SecurityCurrentDataSubject;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigExportReadme;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Takes out what an application cannot run: the bundle is also installed where there is no database, no lock, no Twig or
 * no firewall, and each feature is then absent rather than a container that does not compile.
 */
final class PrivacyServicesPass implements CompilerPassInterface
{
    public const string PROOFS_PARAMETER = 'phil_privacy.consent.proofs';

    /**
     * The tables the schema listener adds, `PhilPrivacyBundle::TABLES`: those of the features left on whose store is the
     * table of the bundle.
     */
    public const string TABLES_PARAMETER = 'phil_privacy.tables';

    /** Whether `account_deletion.enabled` is true in the configuration, rather than on by default. */
    public const string DELETIONS_REQUIRED = 'phil_privacy.account_deletion.required';

    /**
     * Each table of the bundle, by the store that writes it.
     */
    private const array TABLES = [
        DbalAccountDeletions::class => [PrivacyTables::DELETIONS],
        DbalConsentProofs::class => [PrivacyTables::PROOFS],
        DbalGuestRequests::class => [PrivacyTables::GUEST_REQUESTS],
        DbalIncidentRegister::class => [PrivacyTables::INCIDENTS, PrivacyTables::INCIDENT_REVISIONS, PrivacyTables::INCIDENT_JOURNAL],
        DbalEmailTrackingConsents::class => [PrivacyTables::EMAIL_TRACKING],
        DbalErasureRequests::class => [PrivacyTables::ERASURE_REQUESTS, PrivacyTables::ERASURE_STEPS],
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $this->database($container);

        $working = $container->has('clock') && $container->has('lock.factory');
        $deletions = $working && $container->has(AccountDeletionStore::class);

        if (!$deletions) {
            $this->requireDeletions($container);
            $this->remove($container, [AccountDeletions::class, AdvanceAccountDeletionHandler::class, ProcessDueAccountDeletionsHandler::class, ProcessDeletionsCommand::class]);
            $this->unschedule($container, 0);
        }

        if (!$container->has('messenger.default_bus')) {
            $this->remove($container, [ProcessDueAccountDeletionsHandler::class, PrivacySchedule::class]);
        }

        if (!$working) {
            $this->remove($container, [PersonalDataDownload::class, RetentionJob::class, RunRetentionHandler::class, RunRetentionCommand::class]);
            $this->unschedule($container, 1);
        }

        if (!$container->has('clock')) {
            $this->remove($container, [ExportPackage::class, IncidentCommand::class, IncidentNoticeCommand::class, IncidentJournalRecorder::class, IncidentJournalCommand::class]);
        }

        if (!$container->has('twig')) {
            $container->removeDefinition(TwigExportReadme::class);
            $container->setAlias(ExportReadme::class, PlainExportReadme::class);
        }

        if (!$container->has('logger')) {
            $container->removeDefinition(UndeclaredCookieListener::class);
        }

        $this->notices($container);
        $this->guests($container);
        $this->erasureWorkflow($container);
        $this->emailTracking($container);
        $this->pages($container);
        $this->tables($container);
    }

    /**
     * Turned on in the configuration rather than by default, the account deletions need all of their parts: a missing
     * one fails the build rather than leave members without the deletion the configuration promises them.
     */
    private function requireDeletions(ContainerBuilder $container): void
    {
        if ($container->hasParameter(self::DELETIONS_REQUIRED) && true === $container->getParameter(self::DELETIONS_REQUIRED)) {
            throw new LogicException('phil_privacy.account_deletion.enabled is true and needs the lock component (framework.lock), the clock component (symfony/clock) and an AccountDeletionStore: Doctrine DBAL for the table of the bundle, or account_deletion.store.');
        }
    }

    /**
     * Takes one of the two recurring messages out of the schedule, the account deletions (0) or the retention (1), and
     * the schedule itself once it has neither.
     */
    private function unschedule(ContainerBuilder $container, int $message): void
    {
        if (!$container->hasDefinition(PrivacySchedule::class)) {
            return;
        }

        $schedule = $container->getDefinition(PrivacySchedule::class)->replaceArgument($message, null);

        if ([null, null] === $schedule->getArguments()) {
            $container->removeDefinition(PrivacySchedule::class);
        }
    }

    /**
     * The tables of the bundle the application uses: a store of the bundle that no feature left on names is taken out,
     * so that nothing reads a table the schema does not have, and the schema listener adds the tables of the others.
     */
    private function tables(ContainerBuilder $container): void
    {
        $used = [
            DbalAccountDeletions::class => $this->uses($container, AccountDeletions::class, AccountDeletionStore::class),
            DbalConsentProofs::class => $this->uses($container, ConsentRecorder::class, ConsentProofs::class),
            DbalGuestRequests::class => $this->uses($container, GuestRequests::class, GuestRequestStore::class),
            DbalIncidentRegister::class => $this->uses($container, IncidentRegister::class, IncidentRegister::class),
            DbalEmailTrackingConsents::class => $this->uses($container, EmailTrackingConsents::class, EmailTrackingStore::class),
            DbalErasureRequests::class => $this->uses($container, ErasureRequests::class, ErasureRequestStore::class),
        ];

        $tables = [];

        foreach ($used as $store => $inUse) {
            if ($inUse) {
                array_push($tables, ...self::TABLES[$store]);
            } else {
                $this->removeStore($container, $store);
            }
        }

        $container->setParameter(self::TABLES_PARAMETER, $tables);
    }

    /**
     * Takes out a store of the bundle and the ports that still name it.
     */
    private function removeStore(ContainerBuilder $container, string $store): void
    {
        $container->removeDefinition($store);

        foreach ($container->getAliases() as $id => $alias) {
            if ($store === (string) $alias) {
                $container->removeAlias($id);
            }
        }
    }

    /**
     * Whether a feature is there and keeps its records in the store of the bundle that its port names.
     *
     * @param class-string $port
     */
    private function uses(ContainerBuilder $container, string $feature, string $port): bool
    {
        if (!$container->has($feature) || !$container->hasAlias($port)) {
            return false;
        }

        $store = $port;

        while ($container->hasAlias($store)) {
            $store = (string) $container->getAlias($store);
        }

        return \array_key_exists($store, self::TABLES) && $container->hasDefinition($store);
    }

    /**
     * Turned on in the configuration, the requests without an account need all of their parts: a missing one fails the
     * build rather than leave a form that sends mail without its limits or a link that leads nowhere.
     */
    private function guests(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(GuestRequests::class)) {
            return;
        }

        $needs = [
            GuestRequestStore::class => 'a GuestRequestStore: Doctrine DBAL for the table of the bundle, or guest_requests.store',
            GuestLinkSender::class => 'a GuestLinkSender: symfony/mailer for the mailer sender, or guest_requests.sender',
            'lock.factory' => 'the lock component (framework.lock)',
            'clock' => 'the clock component (symfony/clock)',
            ExportPackage::class => 'the clock component (symfony/clock)',
        ];

        if ($container->hasDefinition(MailerGuestLinkSender::class)) {
            $needs += ['mailer.transports' => 'symfony/mailer, for the mailer sender', 'twig' => 'Twig, for the text of the link', 'router' => 'the router, for the address of the link'];
        }

        $cache = $container->getDefinition(GuestRequestServices::PER_CLIENT)->getArgument(1);
        $pool = $cache instanceof Definition ? $cache->getArgument(0) : null;

        if ($pool instanceof Reference) {
            $needs[(string) $pool] = \sprintf('the cache pool "%s", where the limits are counted (guest_requests.cache)', $pool);
        }

        foreach ($needs as $id => $what) {
            if (!$this->exists($container, $id)) {
                throw new LogicException(\sprintf('phil_privacy.guest_requests is enabled and needs %s.', $what));
            }
        }
    }

    /**
     * Turned on in the configuration, the erasure workflow needs the outbox, Messenger, the clock and a store: a missing
     * one fails the build rather than leave requests that nothing runs. The account deletions and the requests made
     * without an account then erase through it.
     */
    private function erasureWorkflow(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ErasureRequests::class)) {
            return;
        }

        $needs = [
            Outbox::class => 'phil/outbox-bundle, on the connection of the bundle',
            ErasureRequestStore::class => 'an ErasureRequestStore: Doctrine DBAL for the tables of the bundle, or erasure_workflow.store',
            'messenger.default_bus' => 'symfony/messenger',
            'clock' => 'the clock component (symfony/clock)',
        ];

        foreach ($needs as $id => $what) {
            if (!$this->exists($container, $id)) {
                throw new LogicException(\sprintf('phil_privacy.erasure_workflow is enabled and needs %s.', $what));
            }
        }

        $workflow = new Reference(ErasureRequests::class);

        if ($container->hasDefinition(AccountDeletions::class)) {
            $container->getDefinition(AccountDeletions::class)->setArgument(11, $workflow);
        }

        if ($container->hasDefinition(GuestRequests::class)) {
            $container->getDefinition(GuestRequests::class)->setArgument(13, $workflow);
        }
    }

    /**
     * Turned on in the configuration, the consent to tracked emails needs a store and a clock: a missing one fails the
     * build rather than leave a page that asks and keeps nothing.
     */
    private function emailTracking(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(EmailTrackingConsents::class)) {
            return;
        }

        $needs = [
            EmailTrackingStore::class => 'an EmailTrackingStore: Doctrine DBAL for the table of the bundle, or email_tracking.store',
            'clock' => 'the clock component (symfony/clock)',
        ];

        foreach ($needs as $id => $what) {
            if (!$this->exists($container, $id)) {
                throw new LogicException(\sprintf('phil_privacy.email_tracking is enabled and needs %s.', $what));
            }
        }
    }

    /**
     * The drafts of the notices are written with Twig and the translator, and read who gives them from the identity of
     * the site of phil/legal-notices when the application has one and names no `NoticeOrganisationSource` of its own.
     */
    private function notices(ContainerBuilder $container): void
    {
        if (!$container->has(NoticeOrganisationSource::class) && $container->has(SiteIdentity::class)) {
            $container->register(SiteIdentityOrganisation::class, SiteIdentityOrganisation::class)->setArguments([new Reference(SiteIdentity::class)]);
            $container->setAlias(NoticeOrganisationSource::class, SiteIdentityOrganisation::class);
        }

        if (!$container->has('twig') || !$container->has('translator')) {
            $this->remove($container, [TwigIncidentNotice::class, IncidentNoticeCommand::class]);
        }
    }

    private function database(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DbalConsentProofs::class)) {
            return;
        }

        $connection = $container->getDefinition(DbalConsentProofs::class)->getArgument(0);

        if ($connection instanceof Reference && $container->has((string) $connection)) {
            return;
        }

        $this->remove($container, [DbalAccountDeletions::class, DbalConsentProofs::class, DbalEmailTrackingConsents::class, DbalGuestRequests::class, DbalIncidentRegister::class, DbalErasureRequests::class, PrivacySchemaListener::class]);

        if ($container->hasAlias(ErasureRequestStore::class) && DbalErasureRequests::class === (string) $container->getAlias(ErasureRequestStore::class)) {
            $container->removeAlias(ErasureRequestStore::class);
        }

        if ($container->hasAlias(IncidentRegister::class) && DbalIncidentRegister::class === (string) $container->getAlias(IncidentRegister::class)) {
            $container->removeAlias(IncidentRegister::class);
            $this->remove($container, [IncidentCommand::class, IncidentsCommand::class, IncidentNoticeCommand::class, IncidentJournalRecorder::class, IncidentJournalCommand::class]);
        }

        if ($container->hasAlias(GuestRequestStore::class) && DbalGuestRequests::class === (string) $container->getAlias(GuestRequestStore::class)) {
            $container->removeAlias(GuestRequestStore::class);
        }

        if (!\is_string($container->getParameter(self::PROOFS_PARAMETER))) {
            $container->setAlias(ConsentProofs::class, LoggedConsentProofs::class);
        }

        if ($container->hasAlias(AccountDeletionStore::class) && DbalAccountDeletions::class === (string) $container->getAlias(AccountDeletionStore::class)) {
            $container->removeAlias(AccountDeletionStore::class);
        }
    }

    private function pages(ContainerBuilder $container): void
    {
        if (!$container->has('clock') || !$container->has('twig') || !$container->has('security.csrf.token_manager') || !$container->has('router')) {
            $this->remove($container, [ConsentRecorder::class, ConsentCookie::class, ConsentContext::class, ConsentVaryListener::class, ConsentController::class, ConsentExtension::class, EmbedExtension::class, PreferencesController::class, GuestRequestController::class, ErasureProgressController::class, PrivacyPages::class, MountingRouteLoader::class]);
        } elseif (!$container->has('security.token_storage') || !$container->has('security.authorization_checker')) {
            $this->remove($container, [SecurityCurrentDataSubject::class, PreferencesController::class]);
            $container->removeAlias(CurrentDataSubject::class);

            if ($container->hasDefinition(MountingRouteLoader::class)) {
                $container->getDefinition(MountingRouteLoader::class)->setArgument(2, false);
            }
        }
    }

    /**
     * Whether a service is there, an alias counting only when what it names is.
     */
    private function exists(ContainerBuilder $container, string $id): bool
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $container->has($id);
    }

    /**
     * @param list<string> $ids
     */
    private function remove(ContainerBuilder $container, array $ids): void
    {
        foreach ($ids as $id) {
            $container->removeDefinition($id);
        }
    }
}
