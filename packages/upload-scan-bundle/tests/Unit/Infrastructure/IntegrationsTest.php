<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage;
use Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck;
use Phil\UploadScanBundle\Tests\Unit\Fixture\MemoryMediaStorage;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The adapters to the bundles the antivirus guards: the media storage and the health check.
 */
#[CoversClass(ScanningMediaStorage::class)]
#[CoversClass(ScannerHealthCheck::class)]
final class IntegrationsTest extends TestCase
{
    public function testAnOriginalIsScannedBeforeItIsStored(): void
    {
        $inner = new MemoryMediaStorage();
        $events = new RecordingDispatcher();
        $storage = new ScanningMediaStorage($inner, new UploadScanner(new FakeScanner(), events: $events));

        $storage->write('originals/01J/1/cover.png', 'an image');

        try {
            $storage->write('originals/01K/1/virus.png', Temporary::eicar());
            self::fail('An infected original was stored.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('The file contains a threat and was refused.', $invalidMedia->getMessage());
        }

        self::assertSame(['originals/01J/1/cover.png' => 'an image'], $inner->files);
        self::assertEquals([new FileInfected('virus.png', 'Eicar-Test-Signature', 'media')], $events->events);
    }

    public function testWhatIsRenderedFromAnOriginalIsNotScannedAgain(): void
    {
        $inner = new MemoryMediaStorage();
        $storage = new ScanningMediaStorage($inner, new UploadScanner(new FakeScanner(error: 'down')));

        $storage->write('formats/01J/1/card.webp', Temporary::eicar());
        $storage->deletePrefix('formats/01J/');

        self::assertSame(Temporary::eicar(), $storage->read('formats/01J/1/card.webp'));
        self::assertNull($storage->read('originals/none'));
        self::assertSame(['formats/01J/'], $inner->deleted);
    }

    public function testAnOriginalThatCouldNotBeScannedIsRefusedOrStoredAsThePolicySays(): void
    {
        $inner = new MemoryMediaStorage();

        try {
            new ScanningMediaStorage($inner, new UploadScanner(new FakeScanner(error: 'down')))->write('originals/a', 'x');
            self::fail('An unscanned original was stored.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame('The file could not be checked for threats. Please try again later.', $invalidMedia->getMessage());
        }

        new ScanningMediaStorage($inner, new UploadScanner(new FakeScanner(error: 'down'), new ScanPolicy(OnScanError::AcceptAndFlag)))->write('originals/b', 'x');

        self::assertSame(['originals/b' => 'x'], $inner->files);
    }

    public function testTheHealthCheckSaysWhetherTheAntivirusAnswers(): void
    {
        $up = new ScannerHealthCheck(new FakeScanner());
        $down = new ScannerHealthCheck(new FakeScanner(reachable: false), 'antivirus');

        self::assertSame(['upload_scan', HealthStatus::Up, 'FakeScanner'], [$up->name(), $up->check()->status, $up->check()->summary]);
        self::assertSame(['antivirus', HealthStatus::Degraded, 'The antivirus does not answer: uploads are refused or flagged.'], [$down->name(), $down->check()->status, $down->check()->summary]);
    }
}
