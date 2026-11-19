<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\PagedMedia;

use Phil\LicensingBundle\Application\Exception\ExportBlocked;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;

/**
 * Stops the print of a work whose licensing does not allow its export, before `phil/paged-media-bundle` starts it: a
 * work without a declaration, or whose declaration does not give what its licenses ask. A subject whose type is
 * declared unlicensed — an invoice, a ticket, a receipt — is printed unchecked.
 */
final readonly class GuardPrintedWork
{
    /**
     * @param list<string> $unlicensedSubjects the subject types printed without a license
     */
    public function __construct(
        private ExportGuard $guard,
        private array $unlicensedSubjects = [],
    ) {
    }

    /**
     * @throws ExportBlocked
     */
    public function __invoke(PrintStarting $event): void
    {
        if (\in_array($event->subject->type, $this->unlicensedSubjects, true)) {
            return;
        }

        $this->guard->assertExportable(SubjectReference::of($event->subject->type, $event->subject->id));
    }
}
