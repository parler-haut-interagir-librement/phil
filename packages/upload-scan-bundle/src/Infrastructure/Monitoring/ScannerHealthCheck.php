<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Monitoring;

use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\UploadScan\Contract\ScannerProbe;

/**
 * The antivirus answers, for `phil/monitoring-bundle`: up with the version of its signatures, degraded when it does not
 * answer — the site still works, but uploads are refused or flagged until it is back.
 */
final readonly class ScannerHealthCheck implements HealthCheck
{
    public function __construct(
        private ScannerProbe $probe,
        private string $name = 'upload_scan',
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $version = $this->probe->version();

        return null === $version
            ? CheckOutcome::degraded('The antivirus does not answer: uploads are refused or flagged.')
            : CheckOutcome::up($version);
    }
}
