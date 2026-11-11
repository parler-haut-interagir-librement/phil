<?php

declare(strict_types=1);

namespace Phil\UploadScan;

/**
 * The decision a scan result leads to: a clean file is accepted, an infected one rejected, whatever the policy; a file
 * that could not be scanned is rejected, or accepted and flagged, as `OnScanError` says.
 */
final readonly class ScanPolicy
{
    public function __construct(
        public OnScanError $onError = OnScanError::Reject,
    ) {
    }

    public function decide(ScanResult $result): Decision
    {
        return match ($result->status) {
            ScanStatus::Clean => Decision::Accept,
            ScanStatus::Infected => Decision::Reject,
            ScanStatus::Error => OnScanError::AcceptAndFlag === $this->onError ? Decision::Flag : Decision::Reject,
        };
    }
}
