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
}
