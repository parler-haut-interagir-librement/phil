<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application;

use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Contract\FactExporter;
use Phil\AuditBundle\Domain\Contract\FeedCheckpoints;
use Phil\AuditBundle\Domain\Export\ExportedFact;

/**
 * Feeds the dashboards: the rows recorded since the feed's place in each segment, read on the key a batch at a time,
 * sent as facts — identifiers, no payload — through a `FactExporter`, and the place moved past each batch once it was
 * sent. A failed batch is sent again on the next run, so a row reaches its destination at least once; its segment and
 * sequence let the receiver drop a row it already has.
 *
 * A run is bounded: it sends at most `$max` rows, and the next run carries on where it stopped.
 */
final readonly class DashboardFeed
{
    public const int DEFAULT_BATCH = 500;

    /**
     * @param string      $name  the feed's name, its checkpoints' key: two feeds to two destinations keep two places
     * @param int<1, max> $batch the rows read and sent at once
     */
    public function __construct(
        private AuditRowRepository $rows,
        private FactExporter $exporter,
        private FeedCheckpoints $checkpoints,
        private string $name = 'dashboards',
        private int $batch = self::DEFAULT_BATCH,
    ) {
    }

    /**
     * Sends what every segment — or the segments named — recorded since the feed's place.
     *
     * @param list<string>|null $segments every segment when `null`
     * @param int               $max      the most rows sent by this run, 1 or more
     *
     * @return array<string, int> the rows sent from each segment that had some
     */
    public function feed(?array $segments = null, int $max = \PHP_INT_MAX): array
    {
        $sent = [];

        foreach ($segments ?? $this->rows->segments() as $segment) {
            $left = $max - array_sum($sent);

            if ($left < 1) {
                break;
            }

            $count = $this->feedSegment($segment, $left);

            if ($count > 0) {
                $sent[$segment] = $count;
            }
        }

        return $sent;
    }

    /**
     * Sends what one segment recorded since the feed's place.
     *
     * @param int $max the most rows sent, 1 or more
     *
     * @return int the rows sent
     */
    public function feedSegment(string $segment, int $max = \PHP_INT_MAX): int
    {
        $sent = 0;

        while ($sent < $max) {
            $rows = $this->rows->rowsAfter($segment, $this->checkpoints->position($this->name, $segment), min($this->batch, $max - $sent));

            if ([] === $rows) {
                break;
            }

            $this->exporter->export(array_map(ExportedFact::of(...), $rows));
            $this->checkpoints->advance($this->name, $segment, $rows[\count($rows) - 1]->sequence);
            $sent += \count($rows);
        }

        return $sent;
    }

    /**
     * How many rows of the segment the feed has not sent yet, read on the key: its head's sequence less its place.
     */
    public function lag(string $segment): int
    {
        $head = $this->rows->head($segment);

        return $head instanceof AuditRow ? max(0, $head->sequence - $this->checkpoints->position($this->name, $segment)) : 0;
    }
}
