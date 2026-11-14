<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use DateTimeImmutable;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AnchorWriter;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;

/**
 * The anchoring job: for every segment, its first and head hashes, written through the `AnchorWriter` to storage the
 * database's administrator cannot rewrite. Run it every day, and at once after a discontinuity. It reads the two ends
 * of each segment only, never the rows between them.
 */
final readonly class Anchoring
{
    public function __construct(
        private AuditRowRepository $rows,
        private AnchorWriter $writer,
    ) {
    }

    /**
     * @return array<string, string> the head hash anchored for each segment
     */
    public function anchor(DateTimeImmutable $periodEnd): array
    {
        $anchored = [];

        foreach ($this->rows->segments() as $segment) {
            $first = $this->rows->first($segment);
            $head = $this->rows->head($segment);

            if ($first instanceof AuditRow && $head instanceof AuditRow) {
                $this->writer->write($segment, $periodEnd, $first->hash, $head->hash);
                $anchored[$segment] = $head->hash;
            }
        }

        return $anchored;
    }
}
