<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Event;

use Phil\UploadScanBundle\Application\Inspection;

/**
 * A file of a `RescanTarget` was scanned again on a worker, after its target resolved the new inspection: the name of
 * the target, the identifier of the file and what the scan found. A package that keeps the files of a target — a media
 * library, an importer of documents — listens to it to lift a flag or take a file down without implementing the
 * target itself; `FileInfected` or `FileFlagged` was dispatched before it, under the context `rescan.<target>`.
 */
final readonly class FileRescanned
{
    public function __construct(
        public string $target,
        public string $fileId,
        public Inspection $inspection,
    ) {
    }

    /**
     * Whether the file was scanned and found clean: a flag set while the scanner was down can be lifted.
     */
    public function cleared(): bool
    {
        return $this->inspection->result->isClean();
    }
}
