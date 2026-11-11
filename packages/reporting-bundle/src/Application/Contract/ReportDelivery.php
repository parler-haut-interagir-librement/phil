<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;

/**
 * A way a report reaches its recipients once exported: `mail` attaches the file to an e-mail, `notification`
 * announces it on the channels of Notifier. Every service implementing it is a delivery, found by its key.
 */
interface ReportDelivery
{
    /**
     * The delivery's key, unique among the deliveries.
     */
    public static function key(): string;

    /**
     * Delivers the export of the job to the job's recipients; `$content` is the file, read from its start.
     */
    public function deliver(ReportJob $job, StoredExport $export, ReportStream $content): void;
}
