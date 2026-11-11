<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Migrator;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\DbalMigrationFactory;
use Doctrine\Migrations\Version\MigrationFactory;
use Override;
use Phil\Migration\Announcements;
use Phil\Migration\AnnouncementsMissing;
use Phil\Migration\Announcer;
use Phil\Migration\Announces;
use Phil\Migration\AnnouncingMigration;
use Phil\Migration\AnnouncingMigrationFactory;
use Phil\Migration\AnnouncingMigrator;
use Phil\Migration\Channel;
use Phil\Migration\Content;
use Phil\Migration\Tests\Unit\Fixtures\Failing\Version1Kept;
use Phil\Migration\Tests\Unit\Fixtures\Migrations\Version1Calendar;
use Phil\Migration\Tests\Unit\Fixtures\Migrations\Version2Plain;
use Phil\Migration\Tests\Unit\Fixtures\Migrations\Version3Skipped;
use Phil\Migration\Tests\Unit\Fixtures\Migrations\Version4Libraries;
use Phil\Migration\Tests\Unit\Fixtures\RecordingPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(AnnouncingMigration::class)]
#[CoversTrait(Announces::class)]
#[CoversClass(AnnouncingMigrationFactory::class)]
#[CoversClass(AnnouncingMigrator::class)]
#[CoversClass(AnnouncementsMissing::class)]
final class MigrationsTest extends TestCase
{
    private RecordingPublisher $publisher;

    private Announcements $announcements;

    #[Override]
    protected function setUp(): void
    {
        $this->publisher = new RecordingPublisher();
        $this->announcements = new Announcements();
    }

    public function testARunIsAnnouncedOnceWithTheMigrationsThatRan(): void
    {
        $sql = $this->migrate('Migrations', leftOver: ['fr' => '* Laissée par un essai.']);

        self::assertSame([Version1Calendar::class, Version2Plain::class, Version3Skipped::class, Version4Libraries::class], array_keys($sql));

        self::assertSame([
            ['alice', "# New release\n\n* Nouveau calendrier.\n* Mise à jour des librairies."],
            ['bob', '* A new calendar.'],
            ['news', "# Le calendrier\nTous les événements.\nhttps://example.com/calendrier"],
        ], $this->publisher->published);
    }

    public function testADryRunAnnouncesNothing(): void
    {
        $this->migrate('Migrations', dryRun: true);

        self::assertSame([], $this->publisher->published);
        self::assertTrue($this->announcements->release([Version1Calendar::class])->isEmpty());
    }

    public function testAFailedRunAnnouncesWhatStays(): void
    {
        try {
            $this->migrate('Failing');
            self::fail('The run did not fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Broken.', $runtimeException->getMessage());
        }

        self::assertSame([['alice', "# New release\n\n* Gardée."]], $this->publisher->published);
    }

    public function testAFailedRunThatWasAllOrNothingAnnouncesNothing(): void
    {
        try {
            $this->migrate('Failing', allOrNothing: true);
            self::fail('The run did not fail.');
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->publisher->published);
    }

    public function testNothingToRunAnnouncesNothing(): void
    {
        $this->migrate('Migrations', untilLatest: false);

        self::assertSame([], $this->publisher->published);
    }

    public function testAMigrationNotGivenTheAnnouncementsSaysSo(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $migration = new DbalMigrationFactory($connection, new NullLogger())->createVersion(Version1Kept::class);

        $this->expectException(AnnouncementsMissing::class);
        $this->expectExceptionMessageIsOrContains(Version1Kept::class.' announces, but has no announcements to add to: decorate the MigrationFactory with '.AnnouncingMigrationFactory::class.'.');

        $migration->up(new Schema());
    }

    /**
     * Runs the migrations of a directory, after a note was left for the first of them by an earlier attempt.
     *
     * @param array<string, string> $leftOver
     *
     * @return array<string, mixed>
     */
    private function migrate(string $directory, bool $dryRun = false, bool $allOrNothing = false, bool $untilLatest = true, array $leftOver = []): array
    {
        $announcements = $this->announcements;

        if ([] !== $leftOver) {
            $announcements->note(Version1Calendar::class, $leftOver);
        }

        $announcer = new Announcer([
            new Channel('alice', 'fr', title: '# New release{counter}'),
            new Channel('bob', 'en'),
            new Channel('news', 'fr', Content::News, heading: '# {title}'),
        ], $this->publisher);

        $configuration = new Configuration();
        $configuration->addMigrationsDirectory('Phil\Migration\Tests\Unit\Fixtures\\'.$directory, __DIR__.'/Fixtures/'.$directory);
        $configuration->setMetadataStorageConfiguration(new TableMetadataStorageConfiguration());

        $factory = DependencyFactory::fromConnection(new ExistingConfiguration($configuration), new ExistingConnection(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])));
        $factory->setDefinition(MigrationFactory::class, static fn (DependencyFactory $factory): MigrationFactory => new AnnouncingMigrationFactory($factory->getMigrationFactory(), $announcements));
        $factory->setDefinition(Migrator::class, static fn (DependencyFactory $factory): Migrator => new AnnouncingMigrator($factory->getMigrator(), $announcements, $announcer));
        $factory->getMetadataStorage()->ensureInitialized();

        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($factory->getVersionAliasResolver()->resolveVersionAlias($untilLatest ? 'latest' : 'current'));

        return $factory->getMigrator()->migrate($plan, new MigratorConfiguration()->setDryRun($dryRun)->setAllOrNothing($allOrNothing));
    }
}
