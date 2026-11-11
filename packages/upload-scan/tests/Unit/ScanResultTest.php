<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\ScanResult;
use Phil\UploadScan\ScanStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScanResult::class)]
#[CoversClass(InvalidArgument::class)]
final class ScanResultTest extends TestCase
{
    public function testACleanResultHasNeitherSignatureNorReason(): void
    {
        $result = ScanResult::clean();

        self::assertSame([ScanStatus::Clean, null, null], [$result->status, $result->signature, $result->reason]);
        self::assertSame([true, false, false], [$result->isClean(), $result->isInfected(), $result->isError()]);
    }

    public function testAnInfectedResultNamesItsSignature(): void
    {
        $result = ScanResult::infected('Win.Test.EICAR_HDB-1');

        self::assertSame([ScanStatus::Infected, 'Win.Test.EICAR_HDB-1', null], [$result->status, $result->signature, $result->reason]);
        self::assertSame([false, true, false], [$result->isClean(), $result->isInfected(), $result->isError()]);
    }

    public function testAnErrorResultGivesItsReason(): void
    {
        $result = ScanResult::error('clamd is down');

        self::assertSame([ScanStatus::Error, null, 'clamd is down'], [$result->status, $result->signature, $result->reason]);
        self::assertSame([false, false, true], [$result->isClean(), $result->isInfected(), $result->isError()]);
    }

    public function testTheTextMayBeOneOr255Bytes(): void
    {
        self::assertSame('x', ScanResult::infected('x')->signature);
        self::assertSame(str_repeat('y', 255), ScanResult::error(str_repeat('y', 255))->reason);
        self::assertSame('a b', ScanResult::error('a b')->reason);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'longer than 255 bytes' => [str_repeat('z', 256)];
        yield 'a control character' => ["a\nb"];
        yield 'a line end after it' => ["a\n"];
        yield 'outside ASCII' => ['é'];
        yield 'a space before' => [' a'];
        yield 'a space after' => ['a '];
    }

    #[DataProvider('refusedTexts')]
    public function testASignatureIsPrintableAscii(string $text): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A scan signature is 1 to 255 bytes of printable ASCII, without spaces around it.');

        ScanResult::infected($text);
    }

    #[DataProvider('refusedTexts')]
    public function testAReasonIsPrintableAscii(string $text): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A scan reason is 1 to 255 bytes');

        ScanResult::error($text);
    }
}
