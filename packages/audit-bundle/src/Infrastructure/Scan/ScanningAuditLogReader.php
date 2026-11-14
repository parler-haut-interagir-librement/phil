<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Scan;

use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\AuditBundle\Domain\Read\Order;

/**
 * The log read by scanning every row of an `AuditRowRepository` that has no index: for an application that keeps its
 * own repository and a small trail, and for tests. Each page reads the whole trail; a long trail belongs in the table,
 * read by `DbalAuditLogReader`.
 */
final readonly class ScanningAuditLogReader implements AuditLogReader
{
    public function __construct(
        private AuditRowRepository $rows,
    ) {
    }

    #[Override]
    public function page(AuditQuery $query): AuditPage
    {
        $segments = null === $query->segment ? $this->rows->segments() : [$query->segment];
        $matching = [];

        foreach ($segments as $segment) {
            foreach ($this->rows->rows($segment) as $row) {
                if ($query->matches($row) && $query->isAfterCursor($row)) {
                    $matching[] = $row;
                }
            }
        }

        usort($matching, static fn (AuditRow $a, AuditRow $b): int => [$a->instant(), $a->segment, $a->sequence] <=> [$b->instant(), $b->segment, $b->sequence]);

        if (Order::NewestFirst === $query->order) {
            $matching = array_reverse($matching);
        }

        $page = \array_slice($matching, 0, $query->limit);

        return new AuditPage($page, \count($matching) > $query->limit ? Cursor::after($page[$query->limit - 1]) : null);
    }

    #[Override]
    public function row(string $segment, int $sequence): ?AuditRow
    {
        foreach ($this->rows->rows($segment) as $row) {
            if ($row->sequence === $sequence) {
                return $row;
            }
        }

        return null;
    }
}
