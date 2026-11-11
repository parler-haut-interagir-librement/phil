<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use RuntimeException;

/**
 * A delivery that keeps what it was given, or fails as a mail server out of reach would.
 */
final class RecordingDelivery implements ReportDelivery
{
    /** @var list<array{ReportJob, StoredExport, string}> */
    public array $delivered = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'record';
    }

    #[Override]
    public function deliver(ReportJob $job, StoredExport $export, ReportStream $content): void
    {
        if ($this->fails) {
            throw new RuntimeException('Out of reach.');
        }

        $this->delivered[] = [$job, $export, $content->contents()];
    }
}
