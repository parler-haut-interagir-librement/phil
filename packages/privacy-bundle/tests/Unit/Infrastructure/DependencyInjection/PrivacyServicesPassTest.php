<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use LogicException;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentJournalCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PrivacyServicesPass::class)]
final class PrivacyServicesPassTest extends TestCase
{
    public function testWithoutADatabaseBothIncidentCommandsGoEvenWithAClock(): void
    {
        $container = $this->withoutDatabase();

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(IncidentCommand::class));
        self::assertFalse($container->hasDefinition(IncidentsCommand::class));
        self::assertFalse($container->hasDefinition(IncidentJournalRecorder::class));
        self::assertFalse($container->hasDefinition(IncidentJournalCommand::class));
        self::assertFalse($container->hasAlias(IncidentRegister::class));
    }

    public function testWithoutAClockTheJournalGoes(): void
    {
        $container = $this->withoutDatabase();
        $container->removeDefinition('clock');
        $container->register('database_connection', stdClass::class);
        $container->register(DbalConsentProofs::class, stdClass::class)->addArgument(new Reference('database_connection'));

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(IncidentJournalRecorder::class));
        self::assertFalse($container->hasDefinition(IncidentJournalCommand::class));
        self::assertTrue($container->hasDefinition(IncidentsCommand::class));
    }

    public function testWithoutADatabaseTheGuestTableIsNoLongerTheStore(): void
    {
        $container = $this->withoutDatabase();

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasAlias(GuestRequestStore::class));
    }

    public function testDeletionsTurnedOnWithoutWhatTheyNeedStopTheBuild(): void
    {
        $container = $this->withoutDatabase();
        $container->setParameter(PrivacyServicesPass::DELETIONS_REQUIRED, true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.account_deletion.enabled is true and needs the lock component (framework.lock), the clock component (symfony/clock) and an AccountDeletionStore: Doctrine DBAL for the table of the bundle, or account_deletion.store.');

        new PrivacyServicesPass()->process($container);
    }

    public function testDeletionsOnByDefaultGoQuietly(): void
    {
        $container = $this->withoutDatabase();
        $container->setParameter(PrivacyServicesPass::DELETIONS_REQUIRED, false);

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasAlias(AccountDeletionStore::class));
    }

    public function testWithoutTheDeletionsTheScheduleKeepsTheRetention(): void
    {
        $container = $this->scheduled();
        $container->register(RetentionJob::class, stdClass::class);

        new PrivacyServicesPass()->process($container);

        self::assertSame([null, '1 day'], $container->getDefinition(PrivacySchedule::class)->getArguments());
    }

    public function testWithoutTheDeletionsNorALockTheScheduleGoes(): void
    {
        $container = $this->scheduled();
        $container->removeDefinition('lock.factory');

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(PrivacySchedule::class));
    }

    public function testWithoutABusTheScheduleGoes(): void
    {
        $container = $this->scheduled();
        $container->removeDefinition('messenger.default_bus');

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(PrivacySchedule::class));
    }

    public function testEveryFeatureOnItsTableGivesItsTables(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, null);
        $this->register($container, ['database_connection', 'clock', 'lock.factory', 'twig', 'security.csrf.token_manager', 'router', 'app.feature', AccountDeletions::class, ConsentRecorder::class]);

        // The requests without an account, the tracked emails and the erasure workflow, each with all of its parts.
        foreach ([GuestRequests::class, EmailTrackingConsents::class, ErasureRequests::class] as $feature) {
            $container->setAlias($feature, 'app.feature');
        }

        $stores = [
            AccountDeletionStore::class => DbalAccountDeletions::class,
            ConsentProofs::class => DbalConsentProofs::class,
            GuestRequestStore::class => DbalGuestRequests::class,
            IncidentRegister::class => DbalIncidentRegister::class,
            EmailTrackingStore::class => DbalEmailTrackingConsents::class,
            ErasureRequestStore::class => DbalErasureRequests::class,
        ];

        foreach ($stores as $port => $store) {
            $container->register($store, stdClass::class)->addArgument(new Reference('database_connection'));
            $container->setAlias($port, $store);
        }

        new PrivacyServicesPass()->process($container);

        self::assertSame(PrivacyTables::ALL, $container->getParameter(PrivacyServicesPass::TABLES_PARAMETER));

        foreach ($stores as $port => $store) {
            self::assertTrue($container->hasDefinition($store), $store);
            self::assertSame($store, (string) $container->getAlias($port));
        }
    }

    public function testAStoreOfTheApplicationTakesTheTablesAway(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, 'app.proofs');
        $this->register($container, ['database_connection', 'clock', 'lock.factory', 'app.store', AccountDeletions::class]);
        $container->setAlias(GuestRequests::class, 'app.store');
        $container->register(DbalAccountDeletions::class, stdClass::class)->addArgument(new Reference('database_connection'));
        $container->register(DbalConsentProofs::class, stdClass::class)->addArgument(new Reference('database_connection'));
        $container->register(DbalGuestRequests::class, stdClass::class)->addArgument(new Reference('database_connection'));
        $container->setAlias(AccountDeletionStore::class, new Alias('app.alias'));
        $container->setAlias('app.alias', 'app.store');
        $container->setAlias(GuestRequestStore::class, 'app.store');
        $container->setAlias('app.other', DbalGuestRequests::class);

        new PrivacyServicesPass()->process($container);

        self::assertSame([], $container->getParameter(PrivacyServicesPass::TABLES_PARAMETER));

        foreach ([DbalAccountDeletions::class, DbalConsentProofs::class, DbalGuestRequests::class] as $store) {
            self::assertFalse($container->hasDefinition($store), $store);
        }

        self::assertFalse($container->hasAlias('app.other'));
        self::assertSame('app.store', (string) $container->getAlias(GuestRequestStore::class));
        self::assertTrue($container->hasDefinition(AccountDeletions::class));
    }

    /**
     * Account deletions off, the retention on: the schedule as the extension writes it.
     */
    private function scheduled(): ContainerBuilder
    {
        $container = $this->withoutDatabase();
        $this->register($container, ['lock.factory', 'messenger.default_bus']);
        $container->register(PrivacySchedule::class, stdClass::class)->setArguments(['15 minutes', '1 day']);

        return $container;
    }

    /**
     * @param list<string> $ids
     */
    private function register(ContainerBuilder $container, array $ids): void
    {
        foreach ($ids as $id) {
            $container->register($id, stdClass::class);
        }
    }

    private function withoutDatabase(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, 'app.proofs');
        $container->register('clock', stdClass::class);
        $container->register(DbalConsentProofs::class, stdClass::class)->addArgument(new Reference('database_connection'));
        $container->register(DbalAccountDeletions::class, stdClass::class)->addArgument(new Reference('database_connection'));
        $container->setAlias(AccountDeletionStore::class, DbalAccountDeletions::class);

        foreach ([DbalIncidentRegister::class, DbalGuestRequests::class, IncidentCommand::class, IncidentsCommand::class, IncidentJournalRecorder::class, IncidentJournalCommand::class] as $id) {
            $container->register($id, stdClass::class);
        }

        $container->setAlias(IncidentRegister::class, DbalIncidentRegister::class);
        $container->setAlias(GuestRequestStore::class, DbalGuestRequests::class);

        return $container;
    }
}
