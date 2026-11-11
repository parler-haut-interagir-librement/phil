<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Decision;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScan\ScanResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScanPolicy::class)]
#[CoversClass(Decision::class)]
final class ScanPolicyTest extends TestCase
{
    public function testByDefaultAFileThatCouldNotBeScannedIsRejected(): void
    {
        $policy = new ScanPolicy();

        self::assertSame(OnScanError::Reject, $policy->onError);
        self::assertSame(Decision::Accept, $policy->decide(ScanResult::clean()));
        self::assertSame(Decision::Reject, $policy->decide(ScanResult::infected('Eicar')));
        self::assertSame(Decision::Reject, $policy->decide(ScanResult::error('down')));
    }

    public function testAcceptedAndFlaggedOnlyWhenItCouldNotBeScanned(): void
    {
        $policy = new ScanPolicy(OnScanError::AcceptAndFlag);

        self::assertSame(Decision::Accept, $policy->decide(ScanResult::clean()));
        self::assertSame(Decision::Reject, $policy->decide(ScanResult::infected('Eicar')));
        self::assertSame(Decision::Flag, $policy->decide(ScanResult::error('down')));
    }

    public function testAFlaggedFileIsKept(): void
    {
        self::assertTrue(Decision::Accept->keeps());
        self::assertTrue(Decision::Flag->keeps());
        self::assertFalse(Decision::Reject->keeps());
        self::assertSame(['accept', 'flag', 'reject'], array_column(Decision::cases(), 'value'));
        self::assertSame(['reject', 'accept_and_flag'], array_column(OnScanError::cases(), 'value'));
    }
}
