<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentJournalCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
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
        $container->register(DbalAccountDeletions::class, stdClass::class)->addArgument(new Reference('database_connection'));

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

    private function withoutDatabase(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, 'app.proofs');
        $container->register('clock', stdClass::class);
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
