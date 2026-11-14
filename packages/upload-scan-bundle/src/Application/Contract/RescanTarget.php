<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Contract;

use Phil\UploadScanBundle\Application\Inspection;

/**
 * Files of the application that can be scanned again later, by identifier: those accepted and flagged while the
 * scanner was down, or every file once the signatures are updated. A `RescanFile` message names the target and the
 * identifier only; the target reads the file where the application keeps it, and decides what its new inspection
 * means — the flag lifted, the file taken down.
 *
 * A service implementing it is tagged `phil_upload_scan.rescan_target` by autoconfiguration.
 */
interface RescanTarget
{
    /**
     * The name messages give the target: an identifier such as `manuscripts`.
     */
    public function name(): string;

    /**
     * The file of the identifier, opened for reading, or `null` when it no longer exists.
     *
     * @return resource|null
     */
    public function open(string $fileId): mixed;

    /**
     * What the new inspection of the file means for the application.
     */
    public function resolve(string $fileId, Inspection $inspection): void;
}
