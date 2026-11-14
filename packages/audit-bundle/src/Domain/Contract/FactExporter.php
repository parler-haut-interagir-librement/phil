<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

use Phil\AuditBundle\Domain\Export\ExportedFact;

/**
 * Sends the facts of the trail to a dashboard, a log pipeline or a security information and event management system.
 * An adapter names its destination — JSON Lines, OpenTelemetry logs —; it receives identifiers only, never a payload.
 */
interface FactExporter
{
    /**
     * Sends a batch, in order. A failure throws: the feed then keeps its place and sends the batch again next time.
     *
     * @param non-empty-list<ExportedFact> $facts
     */
    public function export(array $facts): void;
}
