<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application;

use Phil\UploadScan\Decision;
use Phil\UploadScan\ScanResult;

/**
 * One file inspected: what the scanner answered, what the policy decided, and where an infected file was quarantined.
 */
final readonly class Inspection
{
    /**
     * @param string|null $quarantineId the identifier of the infected file in the quarantine; `null` for a file that is
     *                                  not infected, or when no quarantine is configured or it failed
     */
    public function __construct(
        public ScanResult $result,
        public Decision $decision,
        public ?string $quarantineId = null,
    ) {
    }

    /**
     * Whether the file is kept, flagged or not.
     */
    public function accepted(): bool
    {
        return $this->decision->keeps();
    }

    /**
     * Whether the file is kept although it was not scanned: the application keeps it out of sight, or scans it again.
     */
    public function flagged(): bool
    {
        return Decision::Flag === $this->decision;
    }

    public function infected(): bool
    {
        return $this->result->isInfected();
    }
}
