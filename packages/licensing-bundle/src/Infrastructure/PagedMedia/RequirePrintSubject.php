<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\PagedMedia;

use Phil\LicensingBundle\Application\Exception\PrintSubjectRequired;

/**
 * The strict printing: listens to `UnnamedPrintStarting` and stops a print of `phil/paged-media-bundle` that names no
 * work, whose licensing cannot be checked, before anything is rendered, sent or queued.
 */
final readonly class RequirePrintSubject
{
    /**
     * @throws PrintSubjectRequired
     */
    public function __invoke(): never
    {
        throw new PrintSubjectRequired();
    }
}
