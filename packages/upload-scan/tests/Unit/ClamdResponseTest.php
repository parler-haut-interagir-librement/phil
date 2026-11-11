<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Clamd\ClamdResponse;
use Phil\UploadScan\ScanResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClamdResponse::class)]
final class ClamdResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ScanResult}>
     */
    public static function answers(): iterable
    {
        yield 'clean' => ["stream: OK\0", ScanResult::clean()];
        yield 'clean, with a line end' => ["stream: OK\n", ScanResult::clean()];
        yield 'clean, with spaces and a tab' => ["\t stream: OK \r\n", ScanResult::clean()];
        yield 'infected' => ["stream: Win.Test.EICAR_HDB-1 FOUND\0", ScanResult::infected('Win.Test.EICAR_HDB-1')];
        yield 'infected, read up to the null byte' => ["stream: Eicar FOUND\0stream: OK\0", ScanResult::infected('Eicar')];
        yield 'infected, an unreadable signature' => ["stream: \x01\xFF FOUND\0", ScanResult::infected('??')];
        yield 'infected, no signature' => ["stream: FOUND\0", ScanResult::infected('Unnamed')];
        yield 'infected, spaces before the signature' => ["stream:   Eicar FOUND\0", ScanResult::infected('Eicar')];
        yield 'infected, a space where the signature is cut' => ['stream: '.str_repeat('S', 254)." T FOUND\0", ScanResult::infected(str_repeat('S', 254))];
        yield 'an error, spaces before ERROR' => ["Something  ERROR\0", ScanResult::error('clamd: Something')];
        yield 'infected, a long signature cut' => ['stream: '.str_repeat('S', 300)." FOUND\0", ScanResult::infected(str_repeat('S', 255))];
        yield 'the size limit of clamd' => ["INSTREAM size limit exceeded. ERROR\0", ScanResult::error('clamd: INSTREAM size limit exceeded.')];
        yield 'an error of the stream' => ["stream: Can't allocate memory ERROR\0", ScanResult::error("clamd: stream: Can't allocate memory")];
        yield 'an error without a message' => [" ERROR\0", ScanResult::error('clamd: error')];
        yield 'an error, its message cut' => [str_repeat('E', 300)." ERROR\0", ScanResult::error('clamd: '.str_repeat('E', 248))];
        yield 'nothing' => ['', ScanResult::error('clamd closed the connection without an answer.')];
        yield 'a null byte first' => ["\0stream: OK\0", ScanResult::error('clamd closed the connection without an answer.')];
        yield 'only spaces' => ["  \n", ScanResult::error('clamd closed the connection without an answer.')];
        yield 'another file name' => ["/tmp/file: OK\0", ScanResult::error('clamd answered: /tmp/file: OK')];
        yield 'OK without the stream' => ["OK\0", ScanResult::error('clamd answered: OK')];
        yield 'FOUND without the stream' => ["/tmp/file: Eicar FOUND\0", ScanResult::error('clamd answered: /tmp/file: Eicar FOUND')];
        yield 'clean, with more after it' => ["stream: OK then\0", ScanResult::error('clamd answered: stream: OK then')];
        yield 'an unknown command' => ["UNKNOWN COMMAND\0", ScanResult::error('clamd answered: UNKNOWN COMMAND')];
        yield 'bytes outside ASCII' => ["\xC3\xA9t\xC3\xA9\0", ScanResult::error('clamd answered: ??t??')];
    }

    #[DataProvider('answers')]
    public function testAnAnswerToInstreamIsAResult(string $answer, ScanResult $expected): void
    {
        self::assertEquals($expected, ClamdResponse::scan($answer));
    }

    public function testTheAnswerToPingIsPong(): void
    {
        self::assertTrue(ClamdResponse::isPong("PONG\0"));
        self::assertTrue(ClamdResponse::isPong("PONG\n"));
        self::assertFalse(ClamdResponse::isPong("PING\0"));
        self::assertFalse(ClamdResponse::isPong(''));
    }

    public function testTheAnswerToVersionIsTheVersion(): void
    {
        self::assertSame('ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026', ClamdResponse::version("ClamAV 1.4.1/27450/Sun Nov  8 09:30:02 2026\0"));
        self::assertSame('ClamAV 1.4.1?', ClamdResponse::version("ClamAV 1.4.1\x07\n"));
        self::assertSame('ClamAV '.str_repeat('9', 248), ClamdResponse::version('ClamAV '.str_repeat('9', 400)));
        self::assertNull(ClamdResponse::version("PONG\0"));
        self::assertNull(ClamdResponse::version(''));
    }
}
