<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Contract\ClamdConnector;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\Exception\ScannerUnavailable;
use Phil\UploadScan\Internal\Io;
use Phil\UploadScan\ScanResult;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use Phil\UploadScan\Tests\Unit\Fixture\ScriptedClamd;
use Phil\UploadScan\Tests\Unit\Fixture\SocketPairClamd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClamdScanner::class)]
#[CoversClass(Io::class)]
final class ClamdScannerTest extends TestCase
{
    public function testTheFileIsSentInChunksEachAfterItsLength(): void
    {
        $clamd = new ScriptedClamd();
        $stream = Files::stream('hello');

        self::assertEquals(ScanResult::clean(), new ClamdScanner($clamd, chunkBytes: 2)->scanStream($stream));
        self::assertSame("zINSTREAM\0\0\0\0\2he\0\0\0\2ll\0\0\0\1o\0\0\0\0", $clamd->sent);
        self::assertSame(1, $clamd->connections);
        self::assertIsResource($stream);
    }

    public function testTheChunksDefaultTo64KiB(): void
    {
        $clamd = new ScriptedClamd();

        new ClamdScanner($clamd)->scanStream(Files::stream(str_repeat('a', 65_537)));

        self::assertSame("zINSTREAM\0".pack('N', 65_536).str_repeat('a', 65_536).pack('N', 1).'a'.pack('N', 0), $clamd->sent);
    }

    public function testAnEmptyFileIsSentAsTheLastChunkAlone(): void
    {
        $clamd = new ScriptedClamd();

        self::assertTrue(new ClamdScanner($clamd)->scanStream(Files::stream(''))->isClean());
        self::assertSame("zINSTREAM\0\0\0\0\0", $clamd->sent);
    }

    public function testAnInfectionIsTheSignatureClamdFound(): void
    {
        $result = new ClamdScanner(new ScriptedClamd("stream: Win.Test.EICAR_HDB-1 FOUND\0"))->scanStream(Files::stream('x'));

        self::assertEquals(ScanResult::infected('Win.Test.EICAR_HDB-1'), $result);
    }

    public function testTheAnswerIsReadUpToItsNullByteAndNoMore(): void
    {
        self::assertTrue(new ClamdScanner(new ScriptedClamd("stream: OK\0stream: Eicar FOUND\0"))->scanStream(Files::stream('x'))->isClean());
        self::assertTrue(new ClamdScanner(new ScriptedClamd(str_repeat(' ', 4095)."stream: OK\0"))->scanStream(Files::stream('x'))->isError());
        self::assertTrue(new ClamdScanner(new ScriptedClamd(str_repeat(' ', 4085)."stream: OK\0"))->scanStream(Files::stream('x'))->isClean());
    }

    public function testAnAnswerInPiecesIsReadWhole(): void
    {
        self::assertTrue(new ClamdScanner(new ScriptedClamd("stream: OK\0", piece: 3))->scanStream(Files::stream('x'))->isClean());
        self::assertEquals(ScanResult::infected('Eicar'), new ClamdScanner(new ScriptedClamd("stream: Eicar FOUND\0", piece: 1))->scanStream(Files::stream('x')));
    }

    public function testAFileLargerThanTheLimitIsNotSentWhole(): void
    {
        $clamd = new ScriptedClamd();
        $stream = Files::stream('hello');

        self::assertEquals(ScanResult::error('The file is larger than the 4 bytes the scanner reads.'), new ClamdScanner($clamd, maxBytes: 4, chunkBytes: 2)->scanStream($stream));
        self::assertSame("zINSTREAM\0\0\0\0\2he\0\0\0\2ll", $clamd->sent);
        self::assertTrue(new ClamdScanner(new ScriptedClamd(), maxBytes: 5, chunkBytes: 2)->scanStream(Files::stream('hello'))->isClean());
    }

    public function testTheLimitDefaultsTo25MiB(): void
    {
        $path = Files::path(str_repeat('a', 26_214_401));

        try {
            self::assertEquals(ScanResult::error('The file is larger than the 26214400 bytes the scanner reads.'), new ClamdScanner(new ScriptedClamd())->scanPath($path));
        } finally {
            unlink($path);
        }
    }

    public function testAnUnreachableClamdIsAnError(): void
    {
        $connector = new class implements ClamdConnector {
            public function connect(): mixed
            {
                throw ScannerUnavailable::because('clamd cannot be reached at tcp://clamav:3310: Connection refused'.str_repeat('!', 300));
            }
        };
        $scanner = new ClamdScanner($connector);

        self::assertEquals(ScanResult::error(substr('clamd cannot be reached at tcp://clamav:3310: Connection refused'.str_repeat('!', 300), 0, 255)), $scanner->scanStream(Files::stream('x')));
        self::assertFalse($scanner->ping());
        self::assertNull($scanner->version());
    }

    public function testAConnectionClosedAtOnceIsAnErrorAndTheFileIsNotRead(): void
    {
        $stream = Files::stream('hello');

        $this->expectOutputString('');
        self::assertEquals(ScanResult::error('clamd closed the connection without an answer.'), new ClamdScanner(new ScriptedClamd('', accepted: 0))->scanStream($stream));
        self::assertSame(0, ftell($stream));
    }

    public function testAConnectionClosedDuringTheFileStopsTheSendAndReadsTheAnswer(): void
    {
        $clamd = new ScriptedClamd("INSTREAM size limit exceeded. ERROR\0", accepted: 16);
        $stream = Files::stream('hello world');

        self::assertEquals(ScanResult::error('clamd: INSTREAM size limit exceeded.'), new ClamdScanner($clamd, chunkBytes: 2)->scanStream($stream));
        self::assertSame("zINSTREAM\0\0\0\0\2he", $clamd->sent);
        self::assertSame(4, ftell($stream));
    }

    public function testAConnectionClosedBeforeTheLastChunkStillReadsTheAnswer(): void
    {
        $clamd = new ScriptedClamd("stream: OK\0", accepted: 16);

        self::assertTrue(new ClamdScanner($clamd, chunkBytes: 2)->scanStream(Files::stream('he'))->isClean());
        self::assertSame("zINSTREAM\0\0\0\0\2he", $clamd->sent);
    }

    public function testAWriteAcceptedInPartsIsSentWhole(): void
    {
        $clamd = new ScriptedClamd(stutter: true);

        self::assertTrue(new ClamdScanner($clamd, chunkBytes: 3)->scanStream(Files::stream('hello'))->isClean());
        self::assertSame("zINSTREAM\0\0\0\0\3hel\0\0\0\2lo\0\0\0\0", $clamd->sent);
    }

    public function testAClamdThatDoesNotAnswerTimesOut(): void
    {
        $scanner = new ClamdScanner(new SocketPairClamd(), timeoutSeconds: 1.25);
        $start = hrtime(true);

        self::assertEquals(ScanResult::error('clamd did not answer within 1.25 seconds.'), $scanner->scanStream(Files::stream('x')));
        $elapsed = (hrtime(true) - $start) / 1e9;
        self::assertGreaterThan(1.2, $elapsed);
        self::assertLessThan(2.0, $elapsed);
    }

    public function testAProbeOfAClamdThatDoesNotAnswerTimesOut(): void
    {
        $scanner = new ClamdScanner(new SocketPairClamd(), timeoutSeconds: 0.1);

        self::assertFalse($scanner->ping());
        self::assertNull($scanner->version());
    }

    public function testAStreamAlreadyReadIsScannedFromItsStart(): void
    {
        $clamd = new ScriptedClamd();
        $stream = Files::stream('hello');
        fread($stream, 3);

        self::assertTrue(new ClamdScanner($clamd)->scanStream($stream)->isClean());
        self::assertSame("zINSTREAM\0\0\0\0\5hello\0\0\0\0", $clamd->sent, 'A stream sniffed or hashed first is scanned whole.');

        $clamd = new ScriptedClamd("stream: Eicar FOUND\0");
        $stream = Files::stream('hello');
        stream_get_contents($stream);

        self::assertEquals(ScanResult::infected('Eicar'), new ClamdScanner($clamd)->scanStream($stream), 'A stream read to its end is not an empty file.');
        self::assertSame("zINSTREAM\0\0\0\0\5hello\0\0\0\0", $clamd->sent);
    }

    public function testAStreamThatCannotBeRewoundIsScannedOnlyFromItsStart(): void
    {
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$stream, $writer] = $pair;
        fwrite($writer, 'hello');
        fclose($writer);
        fread($stream, 2);
        $clamd = new ScriptedClamd();

        try {
            self::assertEquals(ScanResult::error('The stream was read before the scan and cannot be rewound: give it to the scanner before reading it.'), new ClamdScanner($clamd)->scanStream($stream));
        } finally {
            fclose($stream);
        }

        self::assertSame(0, $clamd->connections, 'Nothing is sent.');

        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$stream, $writer] = $pair;
        fwrite($writer, 'hello');
        fclose($writer);
        $clamd = new ScriptedClamd();

        try {
            self::assertTrue(new ClamdScanner($clamd)->scanStream($stream)->isClean());
        } finally {
            fclose($stream);
        }

        self::assertSame("zINSTREAM\0\0\0\0\5hello\0\0\0\0", $clamd->sent, 'A stream not yet read is scanned as it comes.');
    }

    public function testAnUnreadableStreamIsAnError(): void
    {
        $stream = opendir(sys_get_temp_dir());
        self::assertIsResource($stream);

        self::assertEquals(ScanResult::error('The file cannot be read.'), new ClamdScanner(new ScriptedClamd())->scanStream($stream));
    }

    public function testAPathIsScannedAndClosed(): void
    {
        $clamd = new ScriptedClamd();
        $path = Files::path('abc');
        $before = \count(get_resources('stream'));

        self::assertTrue(new ClamdScanner($clamd)->scanPath($path)->isClean());
        self::assertSame("zINSTREAM\0\0\0\0\3abc\0\0\0\0", $clamd->sent);
        self::assertCount($before, get_resources('stream'));
        self::assertEquals(ScanResult::error('The file cannot be read.'), new ClamdScanner($clamd)->scanPath('/no/such/file'));
        self::assertEquals(ScanResult::error('The file cannot be read.'), new ClamdScanner($clamd)->scanPath(sys_get_temp_dir()));
        self::assertSame(1, $clamd->connections);
        unlink($path);
    }

    public function testTheConnectionIsClosedAfterEachScan(): void
    {
        $before = \count(get_resources('stream'));
        $stream = Files::stream('x');
        new ClamdScanner(new ScriptedClamd())->scanStream($stream);
        new ClamdScanner(new ScriptedClamd())->ping();

        self::assertCount($before + 1, get_resources('stream'));
    }

    public function testAStreamIsRequired(): void
    {
        $this->expectException(InvalidArgument::class);

        $stream = Files::stream('x');
        fclose($stream);

        new ClamdScanner(new ScriptedClamd())->scanStream($stream);
    }

    public function testPingAndVersionAreCommandsOfTheirOwn(): void
    {
        $pong = new ScriptedClamd("PONG\0");
        $version = new ScriptedClamd("ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026\0");

        self::assertTrue(new ClamdScanner($pong)->ping());
        self::assertSame("zPING\0", $pong->sent);
        self::assertSame('ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026', new ClamdScanner($version)->version());
        self::assertSame("zVERSION\0", $version->sent);
        self::assertFalse(new ClamdScanner($version)->ping());
        self::assertNull(new ClamdScanner($pong)->version());
        self::assertFalse(new ClamdScanner(new ScriptedClamd("PONG\0", accepted: 0))->ping());
    }

    public function testTheScannerAtAnAddressConnectsThere(): void
    {
        $path = Files::socketPath();
        $server = stream_socket_server('unix://'.$path);
        self::assertIsResource($server);

        try {
            self::assertEquals(ScanResult::error('clamd did not answer within 0.1 seconds.'), ClamdScanner::at('unix://'.$path, 0.1)->scanStream(Files::stream('x')));
            self::assertTrue(ClamdScanner::at('unix:///no/such/clamd.sock')->scanStream(Files::stream('x'))->isError());
        } finally {
            fclose($server);
            unlink($path);
        }
    }

    public function testTheTimeoutIsPositive(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('The timeout of clamd is above 0 seconds.');

        new ClamdScanner(new ScriptedClamd(), timeoutSeconds: 0.0);
    }

    public function testTheLimitIsAtLeastOneByte(): void
    {
        self::assertTrue(new ClamdScanner(new ScriptedClamd(), maxBytes: 1, chunkBytes: 1)->scanStream(Files::stream('a'))->isClean());

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('The size limit and the chunks of clamd are at least 1 byte.');

        new ClamdScanner(new ScriptedClamd(), maxBytes: 0);
    }

    public function testTheChunksAreAtLeastOneByte(): void
    {
        $this->expectException(InvalidArgument::class);

        new ClamdScanner(new ScriptedClamd(), chunkBytes: 0);
    }
}
