<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Application\Exception\ExportFailed;
use Phil\AuditBundle\Domain\Contract\FactExporter;
use Phil\AuditBundle\Domain\Export\ExportedFact;

/**
 * Keeps every batch it is given; fails the next `failures` batches.
 */
final class RecordingExporter implements FactExporter
{
    /** @var list<list<ExportedFact>> */
    public array $batches = [];

    public int $failures = 0;

    #[Override]
    public function export(array $facts): void
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw ExportFailed::because('The dashboard is down.');
        }

        $this->batches[] = $facts;
    }

    /**
     * @return list<string> every fact sent, as segment#sequence
     */
    public function places(): array
    {
        $places = [];

        foreach ($this->batches as $batch) {
            foreach ($batch as $fact) {
                $places[] = $fact->segment.'#'.$fact->sequence;
            }
        }

        return $places;
    }
}
