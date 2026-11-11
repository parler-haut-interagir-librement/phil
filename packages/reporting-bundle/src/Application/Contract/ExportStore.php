<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;

/**
 * Where the exported files wait for their delivery — the second half of the claim check: the file itself never
 * travels in a queue.
 */
interface ExportStore
{
    /**
     * Keeps the content of the stream, read from its start, and describes it.
     */
    public function keep(ReportStream $content, string $filename, string $mediaType, int $rows): StoredExport;

    /**
     * The content kept under the identifier, as a stream read from its start; the caller closes it.
     *
     * @throws JobUnavailable when there is no file under the identifier
     */
    public function open(string $id): ReportStream;

    /**
     * Forgets the file; an identifier with no file is ignored.
     */
    public function remove(string $id): void;
}
