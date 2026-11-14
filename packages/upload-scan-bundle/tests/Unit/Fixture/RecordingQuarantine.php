<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Contract\Quarantine;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;

/**
 * Keeps the quarantined files in memory, or refuses them.
 */
final class RecordingQuarantine implements Quarantine
{
    /** @var array<string, array{string, string, ScanResult}> the contents, name and result, by identifier */
    public array $kept = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {
    }

    #[Override]
    public function isolate(mixed $stream, string $name, ScanResult $result): string
    {
        if ($this->fails) {
            throw QuarantineFailed::because('The disk is full.');
        }

        $id = 'q'.(\count($this->kept) + 1);
        $this->kept[$id] = [(string) stream_get_contents($stream), $name, $result];

        return $id;
    }
}
