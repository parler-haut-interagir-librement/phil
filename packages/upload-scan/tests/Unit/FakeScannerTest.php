<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\Internal\Io;
use Phil\UploadScan\ScanResult;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakeScanner::class)]
#[CoversClass(Io::class)]
final class FakeScannerTest extends TestCase
{
    public function testTheEicarStringIsTheStandardOne(): void
    {
        self::assertSame(68, \strlen(FakeScanner::eicar()));
        self::assertSame('275a021bbfb6489e54d471899f7db9d1663fc695ec2fe2a2c4538aabf651fd0f', hash('sha256', FakeScanner::eicar()));
    }

    public function testAFileHoldingTheEicarStringIsInfected(): void
    {
        $scanner = new FakeScanner();

        self::assertEquals(ScanResult::infected('Eicar-Test-Signature'), $scanner->scanStream(Files::stream('before '.FakeScanner::eicar().' after')));
        self::assertEquals(ScanResult::clean(), $scanner->scanStream(Files::stream('a manuscript')));
    }

    public function testAStreamAlreadyReadIsScannedFromItsStart(): void
    {
        $stream = Files::stream(FakeScanner::eicar());
        stream_get_contents($stream);

        self::assertEquals(ScanResult::infected('Eicar-Test-Signature'), new FakeScanner()->scanStream($stream));

        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$piped, $writer] = $pair;
        fwrite($writer, 'X5O!'.FakeScanner::eicar());
        fclose($writer);
        fread($piped, 4);

        try {
            self::assertEquals(ScanResult::error('The stream was read before the scan and cannot be rewound: give it to the scanner before reading it.'), new FakeScanner()->scanStream($piped));
        } finally {
            fclose($piped);
        }
    }

    public function testANeedleGivenIsFoundWithItsSignature(): void
    {
        $scanner = new FakeScanner(['first' => 'Test.First', '42' => 'Test.Number']);

        self::assertEquals(ScanResult::infected('Test.First'), $scanner->scanStream(Files::stream('the first and 42')));
        self::assertEquals(ScanResult::infected('Test.Number'), $scanner->scanStream(Files::stream('only 42')));
        self::assertEquals(ScanResult::infected('Eicar-Test-Signature'), $scanner->scanStream(Files::stream('first '.FakeScanner::eicar())));
        self::assertTrue($scanner->scanStream(Files::stream('nothing'))->isClean());
    }

    public function testAnErrorGivenIsAnsweredForEveryFile(): void
    {
        $scanner = new FakeScanner(error: 'Engine down');
        $stream = Files::stream(FakeScanner::eicar());

        self::assertEquals(ScanResult::error('Engine down'), $scanner->scanStream($stream));
        self::assertSame(0, ftell($stream));
    }

    public function testAFileLargerThanTheLimitIsNotScanned(): void
    {
        self::assertTrue(new FakeScanner(maxBytes: 5)->scanStream(Files::stream('12345'))->isClean());
        self::assertEquals(ScanResult::error('The file is larger than the 5 bytes the scanner reads.'), new FakeScanner(maxBytes: 5)->scanStream(Files::stream('123456')));
    }

    public function testAnUnreadableStreamIsAnError(): void
    {
        $stream = opendir(sys_get_temp_dir());
        self::assertIsResource($stream);

        self::assertEquals(ScanResult::error('The file cannot be read.'), new FakeScanner()->scanStream($stream));
    }

    public function testAPathIsScannedAndClosed(): void
    {
        $scanner = new FakeScanner();
        $path = Files::path(FakeScanner::eicar());
        $before = \count(get_resources('stream'));

        self::assertTrue($scanner->scanPath($path)->isInfected());
        self::assertCount($before, get_resources('stream'));
        self::assertEquals(ScanResult::error('The file cannot be read.'), $scanner->scanPath('/no/such/file'));
        self::assertEquals(ScanResult::error('The file cannot be read.'), $scanner->scanPath(sys_get_temp_dir()));
        unlink($path);
    }

    public function testTheProbeAnswersAsConfigured(): void
    {
        self::assertSame([true, 'FakeScanner'], [new FakeScanner()->ping(), new FakeScanner()->version()]);
        self::assertSame([false, null], [new FakeScanner(reachable: false)->ping(), new FakeScanner(reachable: false)->version()]);
    }

    public function testTheLimitDefaultsToThatOfClamd(): void
    {
        $path = Files::path(str_repeat('a', ClamdScanner::DEFAULT_MAX_BYTES + 1));

        self::assertTrue(new FakeScanner()->scanPath($path)->isError());
        unlink($path);
    }

    public function testAResourceThatIsNotAStreamIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A scanner reads an open stream, not resource (stream-context).');

        new FakeScanner()->scanStream(stream_context_create());
    }

    public function testAnEmptyNeedleIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A needle of the fake scanner is never empty: it would be found in every file.');

        new FakeScanner(['' => 'Everything']);
    }

    public function testALimitBelowOneByteIsRefused(): void
    {
        self::assertTrue(new FakeScanner(maxBytes: 1)->scanStream(Files::stream('a'))->isClean());

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A scanner reads at least 1 byte.');

        new FakeScanner(maxBytes: 0);
    }

    public function testAnUnopenableFileIsAnError(): void
    {
        $path = Files::path('secret');
        chmod($path, 0o000);

        try {
            if (is_readable($path)) {
                self::markTestSkipped('The tests run as a user every file is readable by.');
            }

            $this->expectOutputString('');
            self::assertEquals(ScanResult::error('The file cannot be read.'), new FakeScanner()->scanPath($path));
        } finally {
            chmod($path, 0o600);
            unlink($path);
        }
    }
}
