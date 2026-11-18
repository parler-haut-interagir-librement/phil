<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use DateTimeImmutable;
use JsonException;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ExpiringLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\InterruptingModule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingConsentProofs;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ScriptedLockStore;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(PrivacyRights::class)]
#[CoversClass(PersonalDataDownload::class)]
#[CoversClass(ConsentRecorder::class)]
final class PrivacyRightsAndDownloadTest extends TestCase
{
    public function testEveryRightIsOnByDefaultAndWarnsOfNothing(): void
    {
        $rights = new PrivacyRights();

        self::assertTrue($rights->export);
        self::assertTrue($rights->erasure);
        self::assertSame([], $rights->warnings());
    }

    public function testEachRightTurnedOffWarnsOfItself(): void
    {
        self::assertCount(1, new PrivacyRights(export: false)->warnings());
        self::assertStringContainsString('rights.export is DISABLED', new PrivacyRights(export: false)->warnings()[0]);
        self::assertStringContainsString('rights.erasure is DISABLED', new PrivacyRights(erasure: false)->warnings()[0]);
        self::assertCount(2, new PrivacyRights(false, false)->warnings());
    }

    public function testTheDocumentIsAnArchiveWithAReadmeAndReadableJson(): void
    {
        $document = $this->download(new LockFactory(new InMemoryStore()))->of(DataSubject::fromString('m1'));

        self::assertIsString($document);
        $files = Unzip::files($document);
        self::assertSame(['README.txt', 'members.json'], array_keys($files));
        self::assertStringContainsString('members.json: 3 entries.', $files['README.txt']);
        self::assertStringContainsString("\n", $files['members.json']);
        self::assertStringContainsString('Éloïse', $files['members.json']);
        self::assertStringContainsString('https://example.org/a', $files['members.json']);
    }

    public function testOneExportAtATimePerMemberThenReleased(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $held = $locks->createLock('phil_privacy.export.'.hash('xxh128', 'm1'));
        $held->acquire();

        $download = $this->download($locks);

        self::assertNull($download->of(DataSubject::fromString('m1')));
        self::assertNotNull($download->of(DataSubject::fromString('m2')));
        $held->release();
        self::assertNotNull($download->of(DataSubject::fromString('m1')));
        self::assertTrue($locks->createLock('phil_privacy.export.'.hash('xxh128', 'm1'))->acquire());
    }

    public function testAnExportThatCannotTakeTheLockExportsNothing(): void
    {
        $locks = new ScriptedLockStore();
        $locks->grants = false;

        $exporter = new InterruptingModule('members', static function (): void {
        });

        self::assertNull(new PersonalDataDownload(new SubjectExport([$exporter]), new LockFactory($locks), $this->package())->of(DataSubject::fromString('m1')));
        self::assertSame(0, $exporter->calls);
    }

    public function testTheLockIsReleasedWhenTheDocumentCannotBeWritten(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $download = new PersonalDataDownload(new SubjectExport([new FixedExporter('members', ['name' => "\xB1"])]), $locks, $this->package());

        try {
            $download->of(DataSubject::fromString('m1'));
            self::fail('A document that is not UTF-8 must not pass.');
        } catch (JsonException $jsonException) {
            self::assertTrue($locks->createLock('phil_privacy.export.'.hash('xxh128', 'm1'))->acquire());
            self::assertSame(\JSON_ERROR_UTF8, $jsonException->getCode());
        }
    }

    public function testTheExportLockLastsFiveMinutesAndIsRenewedBeforeEachModule(): void
    {
        $store = new RecordingLockStore();
        $this->download(new LockFactory($store))->of(DataSubject::fromString('m1'));

        self::assertSame([300.0, 300.0], $store->lifetimes);
    }

    public function testADecisionIsDatedOnTheCurrentVersionAndRecorded(): void
    {
        $proofs = new RecordingConsentProofs();
        $choice = $this->recorder($proofs)->decide(['marketing', 'unknown', 'session', 'analytics'], false);

        self::assertSame(['analytics', 'marketing'], $choice->granted);
        self::assertSame('3', $choice->version);
        self::assertFalse($choice->globalPrivacyControl);
        self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00Z'), $choice->decidedAt);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $choice->id);
        self::assertSame([$choice], $proofs->recorded);
    }

    public function testNothingIsGrantedUnderTheGlobalPrivacyControl(): void
    {
        $choice = $this->recorder(new RecordingConsentProofs())->decide(['analytics'], true);

        self::assertSame([], $choice->granted);
        self::assertTrue($choice->globalPrivacyControl);
    }

    public function testAnExportWhoseLockWasTakenOverAnswersAsOneAlreadyRunning(): void
    {
        $clock = new MockClock('2026-11-17T00:00:00Z');
        $locks = new LockFactory(new ExpiringLockStore($clock));
        $name = 'phil_privacy.export.'.hash('xxh128', 'm1');
        $after = new InterruptingModule('posts', static function (): void {
        });
        $taken = new InterruptingModule('members', static function () use ($clock, $locks, $name): void {
            $clock->sleep(PersonalDataDownload::LOCK_TTL + 1);
            self::assertTrue($locks->createLock($name, PersonalDataDownload::LOCK_TTL, false)->acquire());
        });

        self::assertNull(new PersonalDataDownload(new SubjectExport([$taken, $after]), $locks, $this->package())->of(DataSubject::fromString('m1')));
        self::assertSame(0, $after->calls);
    }

    private function download(LockFactory $locks): PersonalDataDownload
    {
        return new PersonalDataDownload(new SubjectExport([new FixedExporter('members', ['name' => 'Éloïse', 'url' => 'https://example.org/a'])]), $locks, $this->package());
    }

    private function package(): ExportPackage
    {
        return new ExportPackage(new PlainExportReadme(), new MockClock('2026-01-01T00:00:00Z'));
    }

    private function recorder(RecordingConsentProofs $proofs): ConsentRecorder
    {
        return new ConsentRecorder(new ConsentPolicy('3', [new ConsentCategory('session', true), new ConsentCategory('analytics'), new ConsentCategory('marketing')]), $proofs, new MockClock('2026-01-01T00:00:00Z'));
    }
}
