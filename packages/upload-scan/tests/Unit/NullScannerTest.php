<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\Internal\Io;
use Phil\UploadScan\NullScanner;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullScanner::class)]
#[CoversClass(Io::class)]
final class NullScannerTest extends TestCase
{
    public function testEveryFileIsClean(): void
    {
        $scanner = new NullScanner();

        self::assertTrue($scanner->scanStream(Files::stream(FakeScanner::eicar()))->isClean());
        self::assertTrue($scanner->scanPath('/no/such/file')->isClean());
    }

    public function testItStillReadsOnlyStreams(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A scanner reads an open stream, not resource (closed).');
        $stream = Files::stream('x');
        fclose($stream);

        new NullScanner()->scanStream($stream);
    }
}
