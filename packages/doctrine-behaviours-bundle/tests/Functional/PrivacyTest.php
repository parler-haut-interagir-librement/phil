<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\Versions;
use Phil\DoctrineBehavioursBundle\Infrastructure\Console\StripVersionsCommand;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\BlamePrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\OwnerPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\PersonalDataFields;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\Stores;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\SuspendBlameWithExport;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\VersionsPrivacy;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Notice;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\TestKernel;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(BlamePrivacy::class)]
#[CoversClass(OwnerPrivacy::class)]
#[CoversClass(Stores::class)]
#[CoversClass(VersionsPrivacy::class)]
#[CoversClass(SuspendBlameWithExport::class)]
#[CoversClass(StripVersionsCommand::class)]
#[CoversClass(PersonalDataFields::class)]
final class PrivacyTest extends KernelTestCase
{
    private EntityManagerInterface $manager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $this->manager = $manager;
    }

    public function testTheBlameIsExportedAndErased(): void
    {
        $page = new Page('home');
        $page->blameCreated('ada');

        $this->manager->persist($page);
        $this->manager->persist(new Page('about'));
        $this->manager->flush();

        $privacy = $this->privacy('test.blame_privacy');
        $ada = DataSubject::fromString('ada');

        self::assertSame('behaviours.blame', $privacy->module()->value);
        self::assertSame(['rows_by_table' => ['page' => 1]], $privacy->export($ada));
        self::assertSame([], $privacy->export(DataSubject::fromString('grace')));

        $report = $privacy->erase($ada);
        self::assertSame('behaviours.blame', $report->module->value);
        self::assertSame(['page' => ErasureEffect::Redacted, 'phil_record_version' => ErasureEffect::Redacted], $report->effects, 'every blamed table, the frozen versions too');
        self::assertSame([], $privacy->export($ada));
        self::assertSame($report->effects, $privacy->erase($ada)->effects, 'a second run reports the same');
        self::assertNull($this->manager->getConnection()->fetchOne("SELECT created_by FROM page WHERE id = 'home'"));
    }

    public function testTheOwnerIsExportedAndHandedToThePseudonym(): void
    {
        $notice = new Notice('Bonjour');
        $notice->claimOwner(new OwnerId('ada'));

        $this->manager->persist($notice);
        $this->manager->flush();

        $privacy = $this->privacy('test.owner_privacy');
        $ada = DataSubject::fromString('ada');

        self::assertSame('behaviours.owner', $privacy->module()->value);
        self::assertSame(['rows_by_table' => ['notice' => 1]], $privacy->export($ada));

        $report = $privacy->erase($ada);
        self::assertSame('behaviours.owner', $report->module->value);
        self::assertSame(['notice' => ErasureEffect::Pseudonymised], $report->effects);
        self::assertSame([], $privacy->export($ada));
        self::assertSame('former', $this->manager->getConnection()->fetchOne('SELECT owner_id FROM notice'));
    }

    public function testASubjectThatCannotOwnOwnsNothing(): void
    {
        $privacy = $this->privacy('test.owner_privacy');
        $subject = DataSubject::fromString('ada@example.org');

        self::assertSame([], $privacy->export($subject));
        self::assertSame(['notice' => ErasureEffect::Pseudonymised], $privacy->erase($subject)->effects);
    }

    public function testTheVersionsOfAnOwnerAreRedactedBeforeTheOwnerIsReplaced(): void
    {
        $notice = new Notice('Bonjour', 'ada@example.org');
        $notice->claimOwner(new OwnerId('ada'));

        $versions = self::getContainer()->get(Versions::class);
        self::assertInstanceOf(Versions::class, $versions);
        $versions->freeze($notice);
        $id = (string) $notice->datedId();
        self::assertSame(['title' => 'Bonjour'], $this->rawState($id), 'a #[PersonalData] field is left out by default');

        $connection = $this->manager->getConnection();
        $connection->executeStatement('UPDATE phil_record_version SET state = ? WHERE subject_id = ?', ['{"title":"Bonjour","contact":"ada@example.org"}', $id]);

        $versionsPrivacy = $this->privacy('test.versions_privacy');
        self::assertSame('behaviours.versions', $versionsPrivacy->module()->value);
        self::assertSame(['versions_with_personal_fields' => 1], $versionsPrivacy->export(DataSubject::fromString('ada')));

        $this->privacy('test.owner_privacy')->erase(DataSubject::fromString('ada'));

        self::assertSame(['title' => 'Bonjour'], $this->rawState($id), 'read in the raw row after the erasure');
        self::assertNotFalse($connection->fetchOne('SELECT redacted_at FROM phil_record_version WHERE subject_id = ?', [$id]));
        self::assertNotNull($connection->fetchOne('SELECT redacted_at FROM phil_record_version WHERE subject_id = ?', [$id]));
        self::assertSame('former', $connection->fetchOne('SELECT owner_id FROM notice'));
        self::assertSame([], $versionsPrivacy->export(DataSubject::fromString('ada')));

        $report = $versionsPrivacy->erase(DataSubject::fromString('ada'));
        self::assertSame(['phil_record_version' => ErasureEffect::Redacted], $report->effects);
        self::assertSame('behaviours.versions', $report->module->value);

        self::assertTrue($versions->get($notice, 1)?->isRedacted());
    }

    public function testTheVersionsTheBlameFindsAreRedactedFirst(): void
    {
        $page = new Page('home');
        $page->blameCreated('ada');

        $this->manager->persist($page);
        $this->manager->flush();

        $connection = $this->manager->getConnection();
        $connection->insert('phil_record_version', ['subject_table' => 'notice', 'subject_id' => 'n', 'version_number' => 1, 'state' => '{"title":"T","contact":"ada@example.org"}', 'frozen_at' => '2026-11-19 13:00:00', 'created_by' => 'ada']);

        $this->privacy('test.blame_privacy')->erase(DataSubject::fromString('ada'));

        self::assertSame(['title' => 'T'], $this->rawState('n'));
        self::assertNull($connection->fetchOne("SELECT created_by FROM phil_record_version WHERE subject_id = 'n'"));
    }

    public function testTheVersionsAlreadyWrittenAreStrippedByTheCommand(): void
    {
        $connection = $this->manager->getConnection();
        $connection->insert('phil_record_version', ['subject_table' => 'notice', 'subject_id' => 'n', 'version_number' => 1, 'state' => '{"title":"T","contact":"ada@example.org"}', 'frozen_at' => '2026-11-19 13:00:00']);

        $application = new Application(self::$kernel ?? throw new LogicException('No kernel.'));
        $tester = new CommandTester($application->find('phil:doctrine-behaviours:strip-versions'));

        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertSame('1 versions hold personal fields in plain.', trim($tester->getDisplay()));
        self::assertSame(['title' => 'T', 'contact' => 'ada@example.org'], $this->rawState('n'));

        self::assertSame(0, $tester->execute([]));
        self::assertSame('1 versions no longer hold personal fields.', trim($tester->getDisplay()));
        self::assertSame(['title' => 'T'], $this->rawState('n'));
        self::assertNull($connection->fetchOne("SELECT redacted_at FROM phil_record_version WHERE subject_id = 'n'"));
    }

    public function testAReportNamesTheModuleWhenNoTableHasTheBehaviour(): void
    {
        self::assertSame(['behaviours.blame' => ErasureEffect::Redacted], Stores::of([], 'behaviours.blame', ErasureEffect::Redacted));
        self::assertSame(['a' => ErasureEffect::Deleted, 'b' => ErasureEffect::Deleted], Stores::of(['a', 'b'], 'm', ErasureEffect::Deleted));
    }

    private function privacy(string $id): PersonalDataEraser&PersonalDataExporter
    {
        $privacy = self::getContainer()->get($id);
        self::assertInstanceOf(PersonalDataEraser::class, $privacy);
        self::assertInstanceOf(PersonalDataExporter::class, $privacy);

        return $privacy;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawState(string $id): array
    {
        $state = $this->manager->getConnection()->fetchOne('SELECT state FROM phil_record_version WHERE subject_id = ?', [$id]);
        self::assertIsString($state);
        $decoded = json_decode($state, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
