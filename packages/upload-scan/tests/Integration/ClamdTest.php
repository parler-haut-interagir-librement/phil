<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Integration;

use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The scanner against a real clamd, at the address of PHIL_UPLOAD_SCAN_CLAMD (`tcp://127.0.0.1:3310`); skipped
 * without it.
 */
#[CoversClass(ClamdScanner::class)]
final class ClamdTest extends TestCase
{
    private ClamdScanner $scanner;

    protected function setUp(): void
    {
        $address = getenv('PHIL_UPLOAD_SCAN_CLAMD');

        if (!\is_string($address) || '' === $address) {
            self::markTestSkipped('PHIL_UPLOAD_SCAN_CLAMD names no clamd.');
        }

        $this->scanner = ClamdScanner::at($address, 30.0, 1_048_576, 4096);
    }

    public function testClamdAnswersItsProbe(): void
    {
        self::assertTrue($this->scanner->ping());
        self::assertStringStartsWith('ClamAV ', (string) $this->scanner->version());
    }

    public function testClamdFindsTheEicarFileAndPassesAnotherOne(): void
    {
        $infected = $this->scanner->scanStream(Files::stream(FakeScanner::eicar()));

        self::assertTrue($infected->isInfected());
        self::assertStringContainsStringIgnoringCase('eicar', (string) $infected->signature);
        self::assertTrue($this->scanner->scanStream(Files::stream(str_repeat('A manuscript. ', 10_000)))->isClean());
    }

    /**
     * ASVS 5.0, 5.2.3: clamd opens archives within archives, and finds the test file three levels down.
     */
    public function testClamdFindsTheEicarFileInsideNestedArchives(): void
    {
        $nested = Files::zip(['outer.zip' => Files::zip(['inner.zip' => Files::zip(['eicar.com' => FakeScanner::eicar()])])]);

        $result = $this->scanner->scanStream(Files::stream($nested));

        self::assertTrue($result->isInfected());
        self::assertStringContainsStringIgnoringCase('eicar', (string) $result->signature);
    }

    /**
     * An archive of more entries than clamd's `MaxFiles` (10 000 by default) is read only up to that limit: the test
     * file after it passes, unless clamd runs with `AlertExceedsMax yes`, which reports the archive as
     * `Heuristics.Limits.Exceeded.MaxFiles`. Set PHIL_UPLOAD_SCAN_CLAMD_ALERT_EXCEEDS_MAX=1 when the clamd under test
     * has it on.
     */
    public function testAnArchiveBeyondMaxFilesPassesUnlessClamdAlertsOnExceededLimits(): void
    {
        $entries = [];

        for ($i = 0; $i < 10_001; ++$i) {
            $entries[\sprintf('f%05d', $i)] = '';
        }

        $entries['eicar.com'] = FakeScanner::eicar();
        $result = ClamdScanner::at((string) getenv('PHIL_UPLOAD_SCAN_CLAMD'), 60.0, 8_388_608)->scanStream(Files::stream(Files::zip($entries)));

        if ('1' === getenv('PHIL_UPLOAD_SCAN_CLAMD_ALERT_EXCEEDS_MAX')) {
            self::assertTrue($result->isInfected());
            self::assertStringContainsString('Exceeded', (string) $result->signature);

            return;
        }

        self::assertTrue($result->isClean(), 'without AlertExceedsMax, clamd stops at MaxFiles and finds nothing');
    }
}
